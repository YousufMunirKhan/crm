/**
 * Export data to CSV file
 * @param {Array} data - Array of objects to export
 * @param {Array} columns - Array of column definitions [{key: 'name', label: 'Name'}, ...]
 * @param {String} filename - Name of the file to download
 */
export function exportToCSV(data, columns, filename = 'export.csv') {
    if (!data || data.length === 0) {
        throw new Error('No data to export');
    }

    // Create CSV header
    const headers = columns.map(col => col.label || col.key).join(',');
    
    // Create CSV rows
    const rows = data.map(item => {
        return columns.map(col => {
            let value = item[col.key];
            
            // Handle nested properties (e.g., customer.name)
            if (col.key.includes('.')) {
                const keys = col.key.split('.');
                value = keys.reduce((obj, key) => obj?.[key], item);
            }
            
            // Format value
            if (value === null || value === undefined) {
                value = '';
            } else if (typeof value === 'object') {
                value = JSON.stringify(value);
            } else {
                value = String(value);
            }
            
            // Escape commas and quotes
            if (value.includes(',') || value.includes('"') || value.includes('\n')) {
                value = `"${value.replace(/"/g, '""')}"`;
            }
            
            return value;
        }).join(',');
    });
    
    // Combine header and rows
    const csvContent = [headers, ...rows].join('\n');
    
    // Create blob and download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    URL.revokeObjectURL(url);
}

