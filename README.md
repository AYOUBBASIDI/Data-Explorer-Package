
# Laravel Data Explorer Package

A powerful Laravel package for managing database exports and imports with ease. The Data Explorer package provides an intuitive web interface for interacting with your database tables, allowing for customized exports in CSV, JSON, and Excel formats. Tailored for developers, database administrators, and analysts, it streamlines data management without the need for SQL knowledge.

---

## Features

1. **Database Table Browsing**:
   - View and select tables from supported databases (MySQL, PostgreSQL, SQLite, and SQL Server).
   - View table metadata, including column details, data types, and row counts.
   - Exclude non-essential tables (e.g., `sessions`, `cache`) from exports.

2. **Data Export Customization**:
   - Export data in CSV, JSON, or Excel formats.
   - Customize exports with:
     - **Filtering**: Apply conditions (e.g., equal to, greater than, starts with).
     - **Column Selection**: Select specific columns for a more targeted export.
   - **File Formatting**:
     - **CSV**: Generated with `League\Csv`.
     - **JSON**: Direct JSON serialization.
     - **Excel (XLSX)**: Created with `PhpSpreadsheet` for enhanced customization (e.g., headers, column formatting).

3. **Error Handling and Validation**:
   - Validates user inputs for tables, columns, and filter conditions.
   - Logs and gracefully handles export errors.

4. **Coming Soon: Import Module**
   - Seamlessly import data into selected tables, with validation and conflict handling.

---

## Installation

> **Note**: This package is still under development and will be published soon.

---

## Usage

Access the Data Explorer via the web interface in your Laravel application. The tool enables you to:

- Browse available tables, view column details, and select tables for export.
- Apply filters and select columns for customized exports.
- Choose your desired export format (CSV, JSON, Excel) and download files.

---

## Requirements

- Laravel 9.x or higher
- Supported database (MySQL, PostgreSQL, SQLite, SQL Server)

## Roadmap

- **Import Module**: Import data into tables with validation and conflict handling (coming soon).

## Contributing

Contributions are welcome! Feel free to open issues or submit pull requests.

---

## License

This package is open-source and available under the [MIT License](LICENSE).
