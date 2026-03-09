// App.js
import { useState } from 'react';
import ProductsTab from './tabs/ProductsTab';
import ImportExportTab from './tabs/ImportExportTab';

function App() {
  const [activeTab, setActiveTab] = useState('products');

  const renderTab = () => {
    switch(activeTab) {
      case 'products': return <ProductsTab />;
      case 'import': return <ImportExportTab />;
      default: return <ProductsTab />;
    }
  };

  return (
    <div className="wbm-app">
      <h1>Barcode Manager</h1>
      <div className="wbm-tabs">
        <button onClick={() => setActiveTab('products')} className={activeTab==='products'?'active':''}>Products</button>
        <button onClick={() => setActiveTab('import')} className={activeTab==='import'?'active':''}>Import / Export</button>
      </div>
      <div className="wbm-tab-content">
        {renderTab()}
      </div>
    </div>
  );
}
export default App;
