<!-- resources/views/vendor/dataexplorer/index.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div id="app" class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-database mr-3 text-indigo-600"></i>
                Data Explorer
                <span class="ml-3 text-sm bg-blue-100 text-blue-800 px-3 py-1 rounded-full">Beta</span>
            </h1>
            <p class="mt-2 text-gray-600">Export and analyze your database tables with ease</p>
        </header>

        <div class="bg-white rounded-xl shadow-lg p-8">
            @if(isset($error))
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">{{ $error }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tabs -->
            <div class="mb-8 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button 
                        @click="activeTab = 'export'"
                        :class="['py-4 px-1 border-b-2 font-medium text-sm', 
                                activeTab === 'export' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                        <i class="fas fa-file-export mr-2"></i>Export Data
                    </button>
                    <button 
                        @click="activeTab = 'import'"
                        :class="['py-4 px-1 border-b-2 font-medium text-sm', 
                                activeTab === 'import' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                        <i class="fas fa-file-import mr-2"></i>Import Data
                        <!-- <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Coming Soon
                        </span> -->
                    </button>
                </nav>
            </div>

            <!-- Export Form -->
            <form v-if="activeTab === 'export'" @submit.prevent="submitForm" class="space-y-8">
                @csrf
                
                <!-- Table and Columns Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Table Selection -->
                    <div class="space-y-4">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-table mr-2 text-indigo-600"></i>Select Table
                        </label>
                        <select 
                            v-model="selectedTable"
                            @change="loadColumns"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Choose a table...</option>
                            <template v-for="table in tables" :key="table.name">
                                <option :value="table.name">
                                    @{{ table.name }} (@{{ table.count }} records)
                                </option>
                            </template>
                        </select>
                    </div>

                    <!-- Filename -->
                    <div class="space-y-4">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-file-alt mr-2 text-indigo-600"></i>Export Filename (Optional)
                        </label>
                        <input 
                            type="text"
                            v-model="filename"
                            placeholder="Enter custom filename"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-sm text-gray-500">
                            Leave empty to use default naming pattern
                        </p>
                    </div>
                </div>

                <!-- Column Selection -->
                <div v-if="selectedTable" class="space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-columns mr-2 text-indigo-600"></i>Select Columns
                        </label>
                        <div class="flex space-x-4">
                            <button 
                                type="button"
                                @click="selectAllColumns"
                                class="text-sm text-indigo-600 hover:text-indigo-500">
                                Select All
                            </button>
                            <button 
                                type="button"
                                @click="deselectAllColumns"
                                class="text-sm text-indigo-600 hover:text-indigo-500">
                                Deselect All
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div v-for="column in columns" :key="column.name" 
                             class="flex items-start p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                            <input
                                type="checkbox"
                                v-model="selectedColumns"
                                :value="column.name"
                                :id="column.name"
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label :for="column.name" class="ml-3">
                                <div class="font-medium text-sm">@{{ column.name }}</div>
                                <div class="text-xs text-gray-500">@{{ column.description }}</div>
                                <div class="text-xs text-gray-400">Type: @{{ column.type }}</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Export Format -->
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-file-export mr-2 text-indigo-600"></i>Export Format
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label v-for="format in exportFormats" :key="format.value" 
                               class="relative flex items-center p-4 cursor-pointer bg-gray-50 border rounded-lg hover:bg-gray-100">
                            <input type="radio" v-model="selectedFormat" :value="format.value" 
                                   class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-3">
                                <span class="block text-sm font-medium text-gray-900">@{{ format.label }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Filters -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-filter mr-2 text-indigo-600"></i>Filters
                        </label>
                        <button 
                            type="button"
                            @click="addFilter"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-plus mr-2"></i> Add Filter
                        </button>
                    </div>

                    <div v-for="(filter, index) in filters" :key="index" 
                         class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                        <select 
                            v-model="filter.column"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select column...</option>
                            <option v-for="column in columns" :key="column.name" :value="column.name">
                                @{{ column.name }}
                            </option>
                        </select>

                        <select 
                            v-model="filter.operator"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select operator...</option>
                            <option v-for="op in operators" :key="op.value" :value="op.value">
                                @{{ op.label }}
                            </option>
                        </select>

                        <input 
                            v-if="!['is null', 'is not null'].includes(filter.operator)"
                            type="text"
                            v-model="filter.value"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Enter value...">

                        <button 
                            type="button"
                            @click="removeFilter(index)"
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-red-600 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class="fas fa-trash mr-2"></i>Remove
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6 border-t border-gray-200">
                    <button 
                        type="submit"
                        :disabled="!isFormValid"
                        class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-download mr-2"></i> Export Data
                    </button>
                </div>
            </form>

            <!-- Import Form -->
            <div v-if="activeTab === 'import'" class="text-center py-12">
                <div v-if="importSuccess" class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">@{{ importSuccess }}</p>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="importError" class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">@{{ importError }}</p>
                        </div>
                    </div>
                </div>

                <!-- File Upload Section -->
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-file-import mr-2 text-indigo-600"></i>Import File
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg" 
                        :class="{'border-indigo-500 bg-indigo-50': isDragging}"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleFileDrop">
                        <div class="space-y-1 text-center">
                            <i class="fas fa-file-upload text-4xl text-gray-400"></i>
                            <div class="flex text-sm text-gray-600">
                                <label class="relative cursor-pointer rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                    <span>Upload a file</span>
                                    <input type="file" class="sr-only" @change="handleFileSelect" 
                                        accept=".csv,.xlsx,.xls,.json">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">
                                CSV, Excel, or JSON up to 10MB
                            </p>
                        </div>
                    </div>
                    <div v-if="selectedFile" class="flex items-center space-x-2 text-sm text-gray-600">
                        <i class="fas fa-file"></i>
                        <span>@{{ selectedFile.name }}</span>
                        <button @click="selectedFile = null" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Table Selection -->
                <div class="space-y-4" v-if="selectedFile">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-table mr-2 text-indigo-600"></i>Select Target Table
                    </label>
                    <select 
                        v-model="importTable"
                        @change="loadTableColumns"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Choose a table...</option>
                        <template v-for="table in tables" :key="table.name">
                            <option :value="table.name">@{{ table.name }}</option>
                        </template>
                    </select>
                </div>

                <!-- Column Mapping -->
                <div v-if="importTable && fileColumns.length" class="space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-columns mr-2 text-indigo-600"></i>Map Columns
                        </label>
                        <button 
                            type="button"
                            @click="autoMapColumns"
                            class="text-sm text-indigo-600 hover:text-indigo-500">
                            Auto-Map Similar Columns
                        </button>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="space-y-4">
                            <div v-for="column in tableColumns" :key="column.name" 
                                class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center p-2 hover:bg-gray-100 rounded">
                                <div>
                                    <span class="font-medium text-sm">@{{ column.name }}</span>
                                    <span class="text-xs text-gray-500 block">@{{ column.description }}</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-arrow-right text-gray-400 mx-4"></i>
                                    <select 
                                        v-model="columnMapping[column.name]"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Skip this column</option>
                                        <option v-for="fileCol in fileColumns" :key="fileCol" :value="fileCol">
                                            @{{ fileCol }}
                                        </option>
                                    </select>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <template v-if="columnMapping[column.name]">
                                        <div v-if="columnPreview[columnMapping[column.name]]" class="text-xs">
                                            Preview: @{{ columnPreview[columnMapping[column.name]] }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import Options -->
                <div v-if="importTable" class="space-y-4">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-cog mr-2 text-indigo-600"></i>Import Options
                    </label>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-4">
                        <div class="flex items-center">
                            <input type="checkbox" v-model="importOptions.skipHeader" 
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label class="ml-2 text-sm text-gray-700">Skip header row</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" v-model="importOptions.validateData" 
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label class="ml-2 text-sm text-gray-700">Validate data before import</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" v-model="importOptions.updateExisting" 
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label class="ml-2 text-sm text-gray-700">Update existing records</label>
                        </div>
                        <div v-if="importOptions.updateExisting" class="ml-6">
                            <label class="block text-sm text-gray-700">Identify records by:</label>
                            <select v-model="importOptions.updateKey" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="column in tableColumns" :key="column.name" :value="column.name">
                                    @{{ column.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6 border-t border-gray-200">
                    <button 
                        @click="startImport"
                        :disabled="!isImportValid || importing"
                        class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-upload mr-2"></i>
                        <span v-if="!importing">Start Import</span>
                        <span v-else>Importing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue

        createApp({
            data() {
                return {
                    activeTab: 'export',
                    tables: @json($tables ?? []),
                    exportFormats: @json($exportFormats),
                    operators: @json($operators),
                    selectedTable: '',
                    selectedFormat: 'csv',
                    filename: '',
                    columns: [],
                    selectedColumns: [],
                    filters: [],
                    loading: false,

                    // Import-related data
                    selectedFile: null,
                    isDragging: false,
                    importTable: '',
                    fileColumns: [],
                    columnMapping: {},
                    tableColumns: [],
                    columnPreview: {},
                    importOptions: {
                        skipHeader: true,
                        validateData: true,
                        updateExisting: false,
                        updateKey: ''
                    },
                    importing: false,
                    importSuccess: '',
                    importError: '',
                    uploadProgress: 0
                }
            },
            computed: {
                isFormValid() {
                    return this.selectedTable && 
                           this.selectedFormat && 
                           this.selectedColumns.length > 0 &&
                           this.filters.every(f => !f.column || !f.operator || 
                               (['is null', 'is not null'].includes(f.operator) || f.value));
                },

                isImportValid() {
                    return this.selectedFile && 
                        this.importTable && 
                        Object.values(this.columnMapping).some(v => v) &&
                        (!this.importOptions.updateExisting || this.importOptions.updateKey);
                }
            },
            methods: {
                loadColumns() {
                    if (this.selectedTable) {
                        const table = this.tables.find(t => t.name === this.selectedTable);
                        this.columns = table ? table.columns : [];
                        this.selectedColumns = this.columns.map(c => c.name);
                        this.filters = [];
                    } else {
                        this.columns = [];
                        this.selectedColumns = [];
                        this.filters = [];
                    }
                },
                selectAllColumns() {
                    this.selectedColumns = this.columns.map(c => c.name);
                },
                deselectAllColumns() {
                    this.selectedColumns = [];
                },
                addFilter() {
                    this.filters.push({
                        column: '',
                        operator: '',
                        value: ''
                    });
                },
                removeFilter(index) {
                    this.filters.splice(index, 1);
                },
                async submitForm() {
                    if (this.loading) return;
                    this.loading = true;

                    try {
                        const exportRoute = "/data-explorer/export";
                        const response = await fetch(exportRoute, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                            },
                            body: JSON.stringify({
                                table: this.selectedTable,
                                format: this.selectedFormat,
                                filters: this.filters,
                                columns: this.selectedColumns,
                                filename: this.filename
                            })
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            throw new Error(error.error || 'Export failed');
                        }

                        console.log(response);
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        const disposition = response.headers.get('Content-Disposition');
                        console.log(disposition)
                        a.download = disposition.split('filename=')[1].replace(/"/g, '');
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        a.remove();
                    } catch (error) {
                        alert('Export failed: ' + error.message);
                    }
                    this.loading = false;
                },
                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.processSelectedFile(file);
                    }
                },
                
                handleFileDrop(event) {
                    this.isDragging = false;
                    const file = event.dataTransfer.files[0];
                    if (file) {
                        this.processSelectedFile(file);
                    }
                },
                
                async processSelectedFile(file) {
                    // Validate file size (10MB limit)
                    if (file.size > 10 * 1024 * 1024) {
                        this.importError = 'File size must be less than 10MB';
                        return;
                    }
                    
                    // Validate file type
                    const validTypes = [
                        'text/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/json'
                    ];
                    
                    if (!validTypes.includes(file.type)) {
                        this.importError = 'Invalid file type. Please upload a CSV, Excel, or JSON file.';
                        return;
                    }
                    
                    this.selectedFile = file;
                    this.importError = '';
                    
                    // Read file headers/preview
                    try {
                        await this.readFilePreview(file);
                    } catch (error) {
                        this.importError = 'Error reading file: ' + error.message;
                        this.selectedFile = null;
                    }
                },
                
                async readFilePreview(file) {
                    const reader = new FileReader();
                    
                    reader.onload = (e) => {
                        try {
                            if (file.type === 'text/csv') {
                                this.parseCSVPreview(e.target.result);
                            } else if (file.type.includes('spreadsheetml.sheet') || file.type.includes('ms-excel')) {
                                this.parseExcelPreview(e.target.result);
                            } else {
                                this.parseJSONPreview(e.target.result);
                            }
                        } catch (error) {
                            this.importError = 'Error parsing file: ' + error.message;
                        }
                    };
                    
                    if (file.type === 'text/csv' || file.type === 'application/json') {
                        reader.readAsText(file);
                    } else {
                        reader.readAsArrayBuffer(file);
                    }
                },
                
                parseCSVPreview(content) {
                    const lines = content.split('\n');
                    if (lines.length > 0) {
                        // Assume first row is headers
                        const headers = lines[0].split(',').map(h => h.trim().replace(/["']/g, ''));
                        this.fileColumns = headers;
                        
                        // Get preview of first data row
                        if (lines.length > 1) {
                            const previewData = lines[1].split(',').map(d => d.trim().replace(/["']/g, ''));
                            this.columnPreview = {};
                            headers.forEach((header, index) => {
                                this.columnPreview[header] = previewData[index];
                            });
                        }
                    }
                },
                
                loadTableColumns() {
                    if (this.importTable) {
                        const table = this.tables.find(t => t.name === this.importTable);
                        this.tableColumns = table ? table.columns : [];
                        this.columnMapping = {};
                        this.autoMapColumns();
                    }
                },
                
                autoMapColumns() {
                    this.tableColumns.forEach(tableCol => {
                        // Find exact or similar column name matches
                        const exactMatch = this.fileColumns.find(
                            fileCol => fileCol.toLowerCase() === tableCol.name.toLowerCase()
                        );
                        
                        if (exactMatch) {
                            this.columnMapping[tableCol.name] = exactMatch;
                        } else {
                            // Look for similar names (e.g., "user_name" matches "username")
                            const similarMatch = this.fileColumns.find(fileCol => {
                                const clean = str => str.toLowerCase().replace(/[_-\s]/g, '');
                                return clean(fileCol) === clean(tableCol.name);
                            });
                            
                            if (similarMatch) {
                                this.columnMapping[tableCol.name] = similarMatch;
                            }
                        }
                    });
                },
                
                async startImport() {
                    if (!this.isImportValid || this.importing) return;
                    
                    this.importing = true;
                    this.importSuccess = '';
                    this.importError = '';
                    
                    const formData = new FormData();
                    formData.append('file', this.selectedFile);
                    formData.append('table', this.importTable);
                    formData.append('mapping', JSON.stringify(this.columnMapping));
                    formData.append('options', JSON.stringify(this.importOptions));
                    
                    try {
                        const response = await fetch('/data-explorer/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                            },
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (response.ok) {
                            this.importSuccess = `Successfully imported ${result.imported} records.`;
                            this.selectedFile = null;
                            this.importTable = '';
                            this.fileColumns = [];
                            this.columnMapping = {};
                        } else {
                            throw new Error(result.error || 'Import failed');
                        }
                    } catch (error) {
                        this.importError = error.message;
                    } finally {
                        this.importing = false;
                    }
                }
            }
        }).mount('#app')
    </script>
</body>
</html>