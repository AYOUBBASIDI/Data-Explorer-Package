<?php

namespace Basidi\DataExplorer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use League\Csv\Writer;
use Illuminate\Support\Str;

class ExportImportController extends Controller
{
    private $excludedTables = [
        'password_resets',
        'failed_jobs',
        'personal_access_tokens',
        'cache',
        'sessions',
        'migrations',
        'job_batches',
        'jobs',
        'cache_locks',
        'password_reset_tokens'
    ];

    public function index()
    {
        $result = $this->getTables();
        $exportFormats = [
            ['value' => 'csv', 'label' => 'CSV (Comma Separated Values)'],
            ['value' => 'json', 'label' => 'JSON (JavaScript Object Notation)'],
            ['value' => 'xlsx', 'label' => 'Excel Spreadsheet']
        ];
        
        $operators = [
            ['value' => '=', 'label' => 'Equal to (=)'],
            ['value' => '!=', 'label' => 'Not equal to (!=)'],
            ['value' => '>', 'label' => 'Greater than (>)'],
            ['value' => '>=', 'label' => 'Greater than or equal to (>=)'],
            ['value' => '<', 'label' => 'Less than (<)'],
            ['value' => '<=', 'label' => 'Less than or equal to (<=)'],
            ['value' => 'like', 'label' => 'Contains (LIKE)'],
            ['value' => 'not like', 'label' => 'Does not contain (NOT LIKE)'],
            ['value' => 'starts with', 'label' => 'Starts with'],
            ['value' => 'ends with', 'label' => 'Ends with'],
            ['value' => 'is null', 'label' => 'Is empty (NULL)'],
            ['value' => 'is not null', 'label' => 'Is not empty (NOT NULL)'],
        ];
        
        if (isset($result['error'])) {
            return view('dataexportimport::index', [
                'tables' => [],
                'exportFormats' => $exportFormats,
                'operators' => $operators,
                'error' => $result['error']
            ]);
        }

        if (empty($result)) {
            return view('dataexportimport::index', [
                'tables' => [],
                'exportFormats' => $exportFormats,
                'operators' => $operators,
                'error' => 'No tables found in the database.'
            ]);
        }

        return view('dataexportimport::index', [
            'tables' => $result,
            'exportFormats' => $exportFormats,
            'operators' => $operators
        ]);
    }

    private function getTables()
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            $tables = match($driver) {
                'mysql' => $this->getMySQLTables(),
                'pgsql' => $this->getPostgresTables(),
                'sqlite' => $this->getSQLiteTables(),
                'sqlsrv' => $this->getSQLServerTables(),
                default => throw new Exception("Unsupported database driver: {$driver}")
            };

            // Filter out excluded tables
            $tables = array_diff($tables, $this->excludedTables);

            return collect($tables)->map(function($tableName) {
                try {
                    $columns = $this->getTableColumns($tableName);
                    $columnDetails = [];
                    
                    foreach ($columns as $column) {
                        $type = Schema::getColumnType($tableName, $column);
                        $columnDetails[] = [
                            'name' => $column,
                            'type' => $type,
                            'description' => $this->getColumnDescription($type)
                        ];
                    }

                    return [
                        'name' => $tableName,
                        'columns' => $columnDetails,
                        'count' => $this->getTableCount($tableName),
                    ];
                } catch (Exception $e) {
                    return [
                        'name' => $tableName,
                        'columns' => [],
                        'count' => 0,
                        'error' => $e->getMessage()
                    ];
                }
            })->filter()->values()->all();

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    private function getColumnDescription($type)
    {
        return match($type) {
            'string' => 'Text value',
            'integer' => 'Whole number',
            'bigint' => 'Large whole number',
            'boolean' => 'True/False value',
            'datetime' => 'Date and time value',
            'date' => 'Date value',
            'decimal' => 'Decimal number',
            'float' => 'Floating point number',
            'text' => 'Long text value',
            default => ucfirst($type)
        };
    }

    /**
     * Get table columns safely
     *
     * @param string $tableName
     * @return array
     */
    private function getTableColumns($tableName)
    {
        try {
            return Schema::getColumnListing($tableName);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get table count safely
     *
     * @param string $tableName
     * @return int
     */
    private function getTableCount($tableName)
    {
        try {
            return DB::table($tableName)->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get tables for MySQL
     *
     * @return array
     */
    private function getMySQLTables()
    {
        $dbName = DB::connection()->getDatabaseName();
        $tables = DB::select('SHOW TABLES');
        $tableKey = "Tables_in_" . $dbName;
        
        return collect($tables)->map(function($table) use ($tableKey) {
            return $table->$tableKey;
        })->toArray();
    }

    /**
     * Get tables for PostgreSQL
     *
     * @return array
     */
    private function getPostgresTables()
    {
        $schema = config('database.connections.pgsql.schema', 'public');
        $tables = DB::select("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = ? 
            AND table_type = 'BASE TABLE'
        ", [$schema]);

        return collect($tables)->pluck('table_name')->toArray();
    }

    /**
     * Get tables for SQLite
     *
     * @return array
     */
    private function getSQLiteTables()
    {
        $tables = DB::select("
            SELECT name 
            FROM sqlite_master 
            WHERE type='table' 
            AND name NOT LIKE 'sqlite_%'
        ");

        return collect($tables)->pluck('name')->toArray();
    }

    /**
     * Get tables for SQL Server
     *
     * @return array
     */
    private function getSQLServerTables()
    {
        $tables = DB::select("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_type = 'BASE TABLE'
        ");

        return collect($tables)->pluck('table_name')->toArray();
    }

    public function export(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'format' => 'required|in:csv,json,xlsx',
            'filters' => 'nullable|array',
            'columns' => 'required|array',
            'filename' => 'nullable|string|max:255',
        ]);

        $table = $request->input('table');
        $format = $request->input('format');
        $filters = $request->input('filters', []);
        $columns = $request->input('columns', []);
        $filename = $request->input('filename');

        try {
            $query = DB::table($table)->select($columns);
            
            foreach ($filters as $filter) {
                if (!empty($filter['column']) && !empty($filter['operator']) && isset($filter['value'])) {
                    switch($filter['operator']) {
                        case 'starts with':
                            $query->where($filter['column'], 'like', $filter['value'] . '%');
                            break;
                        case 'ends with':
                            $query->where($filter['column'], 'like', '%' . $filter['value']);
                            break;
                        case 'is null':
                            $query->whereNull($filter['column']);
                            break;
                        case 'is not null':
                            $query->whereNotNull($filter['column']);
                            break;
                        default:
                            $query->where(
                                $filter['column'],
                                $filter['operator'],
                                $filter['operator'] === 'like' ? '%' . $filter['value'] . '%' : $filter['value']
                            );
                    }
                }
            }

            $data = $query->get();

            // Generate filename
            $baseFilename = $filename ?: Str::slug($table);
            $timestamp = date('Y-m-d_His');
            $finalFilename = "{$baseFilename}_{$timestamp}.{$format}";
            // Export based on format
            return match($format) {
                'json' => $this->exportJson($data, $finalFilename),
                'csv' => $this->exportCsv($data, $finalFilename),
                'xlsx' => $this->exportXlsx($data, $finalFilename),
            };

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function exportJson($data, $name)
    {
        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="' . $name);
    }

    private function exportCsv($data, $name)
    {
        
        $csv = Writer::createFromString('');
        
        // Add headers
        if ($data->count() > 0) {
            $csv->insertOne(array_keys((array) $data->first()));
        }
        
        // Add data
        foreach ($data as $row) {
            $csv->insertOne((array) $row);
        }

        return response($csv->toString())
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $name);
    }

    private function exportXlsx($data, $name)
    {

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Add headers
            if ($data->count() > 0) {
                $columns = array_keys((array) $data->first());
                foreach ($columns as $index => $column) {
                    // Convert numeric index to Excel column letter (A, B, C, etc.)
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->setCellValue($columnLetter . '1', $column);
                }
                
                // Style the header row
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
                $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'E4E4E7',
                        ],
                    ],
                ]);
            }
            
            // Add data
            $row = 2;
            foreach ($data as $record) {
                $col = 1;
                foreach ((array) $record as $value) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    
                    // Handle different data types appropriately
                    if (is_numeric($value)) {
                        $sheet->setCellValueExplicit(
                            $columnLetter . $row,
                            $value,
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                        );
                    } elseif (is_bool($value)) {
                        $sheet->setCellValueExplicit(
                            $columnLetter . $row,
                            $value,
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_BOOL
                        );
                    } elseif ($value instanceof \DateTime) {
                        $sheet->setCellValue($columnLetter . $row, $value);
                        $sheet->getStyle($columnLetter . $row)
                            ->getNumberFormat()
                            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2);
                    } elseif (is_null($value)) {
                        $sheet->setCellValue($columnLetter . $row, '');
                    } else {
                        $sheet->setCellValueExplicit(
                            $columnLetter . $row,
                            (string) $value,
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                    }
                    
                    $col++;
                }
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', $lastColumn) as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            
            // Create Excel file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            // Save to output buffer
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();
            
            // Free up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $name);
                
        } catch (\Exception $e) {
            \Log::error('Excel export failed: ' . $e->getMessage());
            throw new \Exception('Failed to generate Excel file: ' . $e->getMessage());
        }
    }

}