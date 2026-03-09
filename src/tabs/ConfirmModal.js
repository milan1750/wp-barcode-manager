export default function Confirmconfirm({
  isOpen,
  title = "Confirm Action",
  message = "Are you sure?",
  confirmText = "Yes",
  cancelText = "Cancel",
  onConfirm,
  onCancel
}) {
  if (!isOpen) return null;

  return (
    <div className="wbm-confirm">
      <div className="wbm-confirm-header">
        <h3>{title}</h3>
        <button className="wbm-confirm-close" onClick={onCancel}>×</button>
      </div>

      <div className="wbm-confirm-body">
        <p>{message}</p>
      </div>

      <div className="wbm-confirm-footer">
        <button className="button button-secondary" onClick={onCancel}>
          {cancelText}
        </button>
        <button className="button button-primary" onClick={onConfirm}>
          {confirmText}
        </button>
      </div>
    </div>
  );
}
