import { useState } from '@wordpress/element';
import { useToast } from '../ToastContext';
import ConfirmModal from './ConfirmModal';
import * as XLSX from 'xlsx';

export default function DynamicImportExport() {
  const { addToast } = useToast();
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState([]);
  const [totalRows, setTotalRows] = useState(0);
  const [importing, setImporting] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [dragging, setDragging] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  /** ---------- PARSE EXCEL FILE ---------- */
  const parseFile = async (selected) => {
    if (!selected.name.endsWith('.xlsx')) throw new Error('Only Excel (.xlsx) files are supported');
    const data = await selected.arrayBuffer();
    const workbook = XLSX.read(data);
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const arr = XLSX.utils.sheet_to_json(sheet, { header: 1 });
    const headers = arr.shift();
    return arr.map(r => {
      const obj = {};
      headers.forEach((h, i) => (obj[h] = r[i] ?? ''));
      return obj;
    });
  };

  /** ---------- FILE SELECT ---------- */
  const handleFileChange = async (e) => {
    const selected = e.target.files[0];
    if (!selected) return;
    try {
      setFile(selected);
      const rows = await parseFile(selected);
      setTotalRows(rows.length);
      setPreview(rows.slice(0, 5));
    } catch (err) {
      addToast('Error reading file: ' + err.message, 'error');
    }
  };

  /** ---------- DRAG & DROP ---------- */
  const handleDrop = async (e) => {
    e.preventDefault();
    setDragging(false);
    const dropped = e.dataTransfer.files[0];
    if (!dropped) return;
    try {
      setFile(dropped);
      const rows = await parseFile(dropped);
      setTotalRows(rows.length);
      setPreview(rows.slice(0, 5));
    } catch (err) {
      addToast('Error reading file', 'error');
    }
  };

  /** ---------- IMPORT ---------- */
  const handleImport = async () => {
    if (!file) return addToast('Please select a file', 'error');
    setImporting(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await fetch(`${WBM_API.url}products/import-xlsx`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': WBM_API.nonce },
        body: formData,
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Import failed');
      addToast(`Imported ${data.imported || totalRows} rows successfully!`, 'success');
      setFile(null);
      setPreview([]);
      setTotalRows(0);
    } catch (err) {
      addToast(err.message, 'error');
    } finally {
      setImporting(false);
    }
  };

  /** ---------- EXPORT ---------- */
  const handleExport = async () => {
	setExporting(true);
	try {
		const res = await fetch(`${WBM_API.url}products/export-xlsx`, {
		headers: { 'X-WP-Nonce': WBM_API.nonce },
		});
		if (!res.ok) throw new Error('Export failed');

		const blob = await res.blob(); // directly get Excel blob
		const filename = res.headers.get('Content-Disposition')?.split('filename=')[1]?.replace(/"/g,'')
						|| `products-export-${new Date().toISOString().split('T')[0]}.xlsx`;

		const link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = filename;
		link.click();

		addToast('Export successful!', 'success');
	} catch (err) {
		addToast(err.message, 'error');
	} finally {
		setExporting(false);
	}
	};

  return (
    <div className="wbm-import-export-tab">
      <h2>Import / Export Products</h2>

      {/* ---------- Actions ---------- */}
      <div className="wbm-import-export-actions">
        <div
          className={`wbm-dropzone ${dragging ? 'drag-over' : ''}`}
          onDragOver={e => { e.preventDefault(); setDragging(true); }}
          onDragLeave={() => setDragging(false)}
          onDrop={handleDrop}
          onClick={() => document.getElementById('wbm-file-input').click()}
        >
          <p>{file ? file.name : 'Drag & Drop Excel file or Click to Upload'}</p>
          <input
            id="wbm-file-input"
            type="file"
            accept=".xlsx"
            style={{ display: 'none' }}
            onChange={handleFileChange}
          />
        </div>
		<button
          className="wbm-add-btn"
          onClick={() => setConfirmOpen(true)}
          disabled={importing || !file}
        >
          {importing ? 'Importing...' : 'Import'}
        </button>

        <button
          className="wbm-add-btn"
          onClick={handleExport}
          disabled={exporting}
        >
          {exporting ? 'Exporting...' : 'Export'}
        </button>
      </div>

      {/* ---------- Preview Table ---------- */}
      {preview.length > 0 && (
        <div className="wbm-table-wrapper">
          <h3>Preview ({preview.length} of {totalRows} rows)</h3>
          <table className="wbm-table">
            <thead>
              <tr>
                {Object.keys(preview[0]).map(col => (
                  <th key={col}>{col}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {preview.map((row, i) => (
                <tr key={i}>
                  {Object.keys(row).map(col => (
                    <td key={col}><div>{row[col]}</div></td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* ---------- Confirm Modal ---------- */}
      <ConfirmModal
        isOpen={confirmOpen}
        title="Confirm Import"
        message="Are you sure you want to import this file? Existing data may be updated."
        confirmText="Yes, Import"
        cancelText="Cancel"
        onCancel={() => setConfirmOpen(false)}
        onConfirm={() => { setConfirmOpen(false); handleImport(); }}
      />
    </div>
  );
}
