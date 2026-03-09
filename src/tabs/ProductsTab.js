import { useState, useEffect } from '@wordpress/element';
import ReactQuill from 'react-quill';
import ConfirmModal from './ConfirmModal';
import 'react-quill/dist/quill.snow.css';
import { useToast } from '../ToastContext';

const editFields = [
  'Product',
  'Label',
  'Category1',
  'Category2',
  'Ingredients',
  'AllergyAdvice 1 (inc May contain)',
  'AllergyAdvice 2',
  'Storage Information',
  'Price',
  'Eat In Price',
  'BarcodeEAN13',
  'PLU',
  'Print Time',
];

// Fields that need rich text editor
const richTextFields = [
  'Ingredients',
  'AllergyAdvice 1 (inc May contain)',
  'AllergyAdvice 2',
  'Storage Information',
];

const defaultProduct = {
  id: null,
  Product: '',
  Label: '',
  Category1: '',
  Category2: '',
  Ingredients: '',
  'AllergyAdvice 1 (inc May contain)': '',
  'AllergyAdvice 2': '',
  'Storage Information': '',
  Price: '',
  'Eat In Price': '',
  BarcodeEAN13: '',
  PLU: '',
  'Print Time': '',
};


export default function ProductsTab() {
  const { addToast } = useToast();
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [editingProduct, setEditingProduct] = useState(null);
  const [activeField, setActiveField] = useState(null);
  const perPage = 10;
	const [deleteId, setDeleteId] = useState(null);
const [showConfirm, setShowConfirm] = useState(false);
  useEffect(() => {
    fetchProducts();
  }, [currentPage]);

  const fetchProducts = async () => {
    setLoading(true);
    try {
      const res = await fetch(
        `${WBM_API.url}products?page=${currentPage}&per_page=${perPage}`,
        { headers: { 'X-WP-Nonce': WBM_API.nonce } }
      );
      if (!res.ok) throw new Error('Failed to fetch products');
      const data = await res.json();
      setProducts(data.data || []);
      setTotalPages(data.total_pages || 1);
    } catch (err) {
      addToast(err.message || 'Error fetching products', 'error');
    } finally {
      setLoading(false);
    }
  };

 const handleDelete = async () => {
  if (!deleteId) return;

  try {
    const res = await fetch(`${WBM_API.url}products/${deleteId}`, {
      method: 'DELETE',
      headers: { 'X-WP-Nonce': WBM_API.nonce },
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Delete failed');

    addToast('Product deleted!', 'success');
    fetchProducts();
  } catch (err) {
    addToast(err.message || 'Delete failed', 'error');
  } finally {
    setShowConfirm(false);
    setDeleteId(null);
  }
};

  const handleSave = async () => {
    if (!editingProduct.Product || !editingProduct.PLU) {
      addToast('Product and PLU required', 'error');
      return;
    }

    const payload = { ...defaultProduct, ...editingProduct };
    const url = editingProduct.id
      ? `${WBM_API.url}products/${editingProduct.id}`
      : `${WBM_API.url}products`;
    const method = editingProduct.id ? 'PUT' : 'POST';

    try {
      const res = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': WBM_API.nonce,
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Save failed');

      addToast('Saved successfully!', 'success');
      setEditingProduct(null);
      setActiveField(null);
      fetchProducts();
    } catch (err) {
      addToast(err.message || 'Save failed', 'error');
    }
  };

  // Utility to render cell HTML safely
  const renderCell = (value) => {
    return <div dangerouslySetInnerHTML={{ __html: value || '-' }} />;
  };

  return (
    <div className="wbm-product-tab">
      <button
        className="wbm-add-btn"
        onClick={() => setEditingProduct({ ...defaultProduct })}
      >
        Add Product
      </button>

      {loading ? (
        <div className="wbm-loader">
          {Array.from({ length: perPage }).map((_, i) => (
            <div key={i} className="wbm-loader-row">
              {Array.from({ length: 8 }).map((_, j) => (
                <div key={j} className="wbm-loader-cell" />
              ))}
            </div>
          ))}
        </div>
      ) : (
        <>
          <div className="wbm-table-wrapper">
            <table className="wbm-table">
              <thead>
                <tr>
                  <th>PLU</th>
                  <th>Product</th>
                  <th>Category1</th>
                  <th>Category2</th>
                  <th>Price</th>
                  <th>Eat In Price</th>
                  <th>Barcode</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {products.length ? (
                  products.map((p) => (
                    <tr key={p.id}>
                      <td>{renderCell(p.PLU)}</td>
                      <td>{renderCell(p.Product)}</td>
                      <td>{renderCell(p.Category1)}</td>
                      <td>{renderCell(p.Category2)}</td>
                      <td>{renderCell(p.Price)}</td>
                      <td>{renderCell(p['Eat In Price'])}</td>
                      <td>{renderCell(p.BarcodeEAN13)}</td>
                      <td>
                        <button
                          onClick={() =>
                            setEditingProduct({ ...defaultProduct, ...p })
                          }
                        >
                          Edit
                        </button>
                        <button
                          className="delete-btn"
                          onClick={() => {
							setDeleteId(p.id);
							setShowConfirm(true);
							}}
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={8}>No products found.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="wbm-pagination">
            <button
              onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
              disabled={currentPage === 1}
            >
              Prev
            </button>
            <span>
              Page {currentPage} of {totalPages}
            </span>
            <button
              onClick={() =>
                setCurrentPage((p) => Math.min(p + 1, totalPages))
              }
              disabled={currentPage === totalPages}
            >
              Next
            </button>
          </div>
        </>
      )}

      {/* Edit Modal */}
      {editingProduct && (
        <div className="wbm-modal">
          <div className="wbm-modal-content">
            <div className="wbm-modal-header">
              {editingProduct.id ? 'Edit Product' : 'Add Product'}
            </div>

            <div className="wbm-modal-body">
              <form className="wbm-edit-form">
                {editFields.map((key) => (
                  <div key={key} className="wbm-form-group">
                    <label>{key}</label>

                    {richTextFields.includes(key) ? (
                      activeField === key ? (
                        <ReactQuill
                          theme="snow"
                          value={editingProduct[key] || ''}
                          onChange={(content) =>
                            setEditingProduct({
                              ...editingProduct,
                              [key]: content,
                            })
                          }
                        />
                      ) : (
                        <div
                          className="wbm-placeholder"
                          dangerouslySetInnerHTML={{
                            __html: editingProduct[key] || 'Click to edit',
                          }}
                          onClick={() => setActiveField(key)}
                        />
                      )
                    ) : (
                      <input
                        type="text"
                        value={editingProduct[key] || ''}
                        onChange={(e) =>
                          setEditingProduct({
                            ...editingProduct,
                            [key]: e.target.value,
                          })
                        }
                      />
                    )}
                  </div>
                ))}
              </form>
            </div>

            <div className="wbm-modal-footer">
              <button onClick={handleSave}>
                {editingProduct.id ? 'Update' : 'Save'}
              </button>
              <button
                onClick={() => {
                  setEditingProduct(null);
                  setActiveField(null);
                }}
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}

	  <ConfirmModal
  isOpen={showConfirm}
  title="Delete Product"
  message="Are you sure you want to delete this product?"
  confirmText="Delete"
  cancelText="Cancel"
  onConfirm={handleDelete}
  onCancel={() => {
    setShowConfirm(false);
    setDeleteId(null);
  }}
/>
    </div>
  );
}
