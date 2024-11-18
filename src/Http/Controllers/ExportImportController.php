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

    public function import(Request $request)
    {
        try {
            // Validate basic request parameters
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:10240', // Max 10MB
                'table' => 'required|string',
                'mapping' => 'required|json',
                'options' => 'required|json'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            // Validate table exists
            if (!Schema::hasTable($request->table)) {
                return response()->json(['error' => 'Selected table does not exist'], 422);
            }

            // Parse options and column mapping
            $options = json_decode($request->options, true);
            $columnMapping = json_decode($request->mapping, true);

            // Get table columns info for validation
            $tableColumns = $this->getTableColumns($request->table);

            // Process file based on type
            $fileData = $this->processFile($request->file('file'), $options['skipHeader'] ?? true);

            // Start database transaction
            DB::beginTransaction();

            $importedCount = 0;
            $errors = [];
            $batchSize = 1000; // Process in batches of 1000
            $batch = [];

            foreach ($fileData as $index => $row) {
                $processedRow = $this->processRow($row, $columnMapping, $tableColumns);
                
                if ($processedRow['errors']) {
                    $errors[] = [
                        'row' => $index + 1,
                        'errors' => $processedRow['errors']
                    ];
                    continue;
                }

                // Handle existing records if update option is enabled
                if ($options['updateExisting'] && !empty($options['updateKey'])) {
                    $existingRecord = DB::table($request->table)
                        ->where($options['updateKey'], $processedRow['data'][$options['updateKey']])
                        ->first();

                    if ($existingRecord) {
                        DB::table($request->table)
                            ->where($options['updateKey'], $processedRow['data'][$options['updateKey']])
                            ->update($processedRow['data']);
                        $importedCount++;
                        continue;
                    }
                }

                $batch[] = $processedRow['data'];

                // Insert batch when it reaches batch size
                if (count($batch) >= $batchSize) {
                    DB::table($request->table)->insert($batch);
                    $importedCount += count($batch);
                    $batch = [];
                }
            }

            // Insert remaining records
            if (!empty($batch)) {
                DB::table($request->table)->insert($batch);
                $importedCount += count($batch);
            }

            // If validation is enabled and there are errors, rollback
            if ($options['validateData'] && !empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $errors
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => 'Import completed successfully',
                'imported' => $importedCount
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Process uploaded file and return data array
     *
     * @param UploadedFile $file
     * @param bool $skipHeader
     * @return array
     */
    private function processFile($file, $skipHeader = true)
    {
        $extension = $file->getClientOriginalExtension();
        $data = [];

        switch (strtolower($extension)) {
            case 'csv':
                $handle = fopen($file->getPathname(), 'r');
                if ($skipHeader) {
                    fgetcsv($handle); // Skip header row
                }
                while (($row = fgetcsv($handle)) !== false) {
                    $data[] = $row;
                }
                fclose($handle);
                break;

            case 'xlsx':
            case 'xls':
                $spreadsheet = IOFactory::load($file->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                $data = $skipHeader ? array_slice($rows, 1) : $rows;
                break;

            case 'json':
                $content = file_get_contents($file->getPathname());
                $jsonData = json_decode($content, true);
                $data = is_array($jsonData) ? $jsonData : [$jsonData];
                break;

            default:
                throw new Exception('Unsupported file format');
        }

        return $data;
    }

    /**
     * Process a single row of data
     *
     * @param array $row
     * @param array $columnMapping
     * @param array $tableColumns
     * @return array
     */
    private function processRow($row, $columnMapping, $tableColumns)
    {
        $processedData = [];
        $errors = [];

        foreach ($columnMapping as $tableColumn => $fileColumn) {
            if (empty($fileColumn)) {
                continue; // Skip unmapped columns
            }

            $value = is_array($row) ? 
                (isset($row[$fileColumn]) ? $row[$fileColumn] : null) : 
                $row;

            // Validate and cast value based on column type
            $columnInfo = $tableColumns[$tableColumn] ?? null;
            if ($columnInfo) {
                $validationResult = $this->validateAndCastValue($value, $columnInfo);
                if ($validationResult['error']) {
                    $errors[] = "Column '$tableColumn': " . $validationResult['error'];
                } else {
                    $processedData[$tableColumn] = $validationResult['value'];
                }
            }
        }

        return [
            'data' => $processedData,
            'errors' => $errors
        ];
    }

    /**
     * Get table columns information
     *
     * @param string $table
     * @return array
     */
    // private function getTableColumns($table)
    // {
    //     $columns = [];
    //     $columnInfo = DB::select("SHOW COLUMNS FROM $table");

    //     foreach ($columnInfo as $column) {
    //         $columns[$column->Field] = [
    //             'type' => $column->Type,
    //             'nullable' => $column->Null === 'YES',
    //             'default' => $column->Default,
    //         ];
    //     }

    //     return $columns;
    // }

    /**
     * Validate and cast value based on column type
     *
     * @param mixed $value
     * @param array $columnInfo
     * @return array
     */
    private function validateAndCastValue($value, $columnInfo)
    {
        if ($value === null || $value === '') {
            if (!$columnInfo['nullable']) {
                return ['error' => 'Value cannot be null', 'value' => null];
            }
            return ['error' => null, 'value' => null];
        }

        $type = strtolower(preg_replace('/\(.*\)/', '', $columnInfo['type']));

        switch ($type) {
            case 'int':
            case 'integer':
            case 'smallint':
            case 'bigint':
                if (!is_numeric($value)) {
                    return ['error' => 'Value must be numeric', 'value' => null];
                }
                return ['error' => null, 'value' => (int)$value];

            case 'decimal':
            case 'float':
            case 'double':
                if (!is_numeric($value)) {
                    return ['error' => 'Value must be numeric', 'value' => null];
                }
                return ['error' => null, 'value' => (float)$value];

            case 'date':
                try {
                    $date = new \DateTime($value);
                    return ['error' => null, 'value' => $date->format('Y-m-d')];
                } catch (\Exception $e) {
                    return ['error' => 'Invalid date format', 'value' => null];
                }

            case 'datetime':
            case 'timestamp':
                try {
                    $date = new \DateTime($value);
                    return ['error' => null, 'value' => $date->format('Y-m-d H:i:s')];
                } catch (\Exception $e) {
                    return ['error' => 'Invalid datetime format', 'value' => null];
                }

            case 'boolean':
            case 'tinyint':
                if (is_bool($value)) {
                    return ['error' => null, 'value' => $value];
                }
                if (in_array(strtolower($value), ['1', 'true', 'yes', 'on'])) {
                    return ['error' => null, 'value' => true];
                }
                if (in_array(strtolower($value), ['0', 'false', 'no', 'off'])) {
                    return ['error' => null, 'value' => false];
                }
                return ['error' => 'Invalid boolean value', 'value' => null];

            default:
                // For string types (varchar, text, etc.)
                return ['error' => null, 'value' => (string)$value];
        }
    }

}