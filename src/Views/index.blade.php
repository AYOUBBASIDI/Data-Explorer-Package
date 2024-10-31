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
                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Coming Soon
                        </span>
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

            <!-- Import Form (Coming Soon) -->
            <div v-if="activeTab === 'import'" class="text-center py-12">
                <div class="rounded-full bg-yellow-100 p-3 inline-block">
                    <i class="fas fa-tools text-yellow-600 text-3xl"></i>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900">Coming Soon</h3>
                <p class="mt-2 text-sm text-gray-500">
                    The import functionality is currently under development.<br>
                    Stay tuned for updates!
                </p>
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
                    loading: false
                }
            },
            computed: {
                isFormValid() {
                    return this.selectedTable && 
                           this.selectedFormat && 
                           this.selectedColumns.length > 0 &&
                           this.filters.every(f => !f.column || !f.operator || 
                               (['is null', 'is not null'].includes(f.operator) || f.value));
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
                        const exportRoute = "{{ route('data-explorer.export') }}";
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
                }
            }
        }).mount('#app')
    </script>
</body>
</html>