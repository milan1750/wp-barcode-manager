# WP Barcode Manager REST API

A REST API handler for **WP Barcode Manager** that manages products and
supports **Excel (XLSX) import/export with basic text formatting**.

This API allows WordPress administrators to create, update, delete,
import, and export products while preserving basic rich text styles
like:

- **Bold**
- _Italic_
- Underline
- Text color

The system stores products as a **custom post type (`wbm_product`)** and
uses **post meta** for product fields.

---

# Features

- Full **REST API CRUD** for products
- **Excel (XLSX) Import**
- **Excel (XLSX) Export**
- Preserves basic **rich text styles**
- Automatic **EAN‑13 barcode generation**
- WordPress **nonce security**
- Sanitized input for safe HTML storage

---

# Product Fields

Field

---

Label
Category1
Category2
Product
Ingredients
AllergyAdvice 1 (inc May contain)
AllergyAdvice 2
Price
Eat In Price
BarcodeEAN13
Storage Information
PLU

---

# HTML Supported Fields

The following fields allow HTML formatting:

- Product
- Ingredients
- AllergyAdvice 1 (inc May contain)
- AllergyAdvice 2
- Storage Information

Supported formatting includes:

```html
<strong>Bold</strong>
<em>Italic</em>
<u>Underline</u>
<span style="color:#ff0000">Colored text</span>
```

---

# REST API Namespace

    /wp-json/wbm/v1/

---

# API Endpoints

## Get Products

    GET /products

### Query Parameters

Parameter Description

---

page Page number
per_page Number of items per page

### Example Response

```json
{
  "page": 1,
  "per_page": 10,
  "total": 100,
  "total_pages": 10,
  "data": []
}
```

---

# Create Product

    POST /products

### Required Fields

- `Product`
- `PLU`

### Example Request

```json
{
  "Product": "Chocolate Cake",
  "PLU": "12345",
  "Price": "4.99"
}
```

If `BarcodeEAN13` is missing it will be **automatically generated**.

---

# Update Product

    PUT /products/{id}

Example:

    PUT /products/123

---

# Delete Product

    DELETE /products/{id}

### Response

```json
{
  "deleted": true,
  "id": 123
}
```

---

# Import Products from Excel

    POST /products/import-xlsx

### Upload

Send a **multipart/form-data** request with:

    file: products.xlsx

### Import Logic

- Matches products using **PLU**
- Existing product → **updated**
- New product → **created**
- Rows without `Product` or `PLU` → **skipped**

Example response:

```json
{
  "imported": 10,
  "updated": 5,
  "skipped": 2
}
```

---

# Export Products to Excel

    GET /products/export-xlsx

Downloads an **XLSX file** containing all products.

Example filename:

    products-export-YYYY-MM-DD.xlsx

---

# Barcode Generation

The system generates a **valid EAN‑13 barcode** automatically if none is
provided.

Steps:

1.  Generate 12 random digits
2.  Calculate the EAN‑13 check digit
3.  Ensure uniqueness in the database

---

# Security

All endpoints require:

Capability:

    manage_options

Nonce header:

    X-WP-Nonce

Example:

```js
headers: {
  "X-WP-Nonce": wpApiSettings.nonce
}
```

---

# Technologies Used

- WordPress REST API
- PHP
- React (Admin UI)
- PhpSpreadsheet
- XLSX Import/Export

Library:

    PhpOffice/PhpSpreadsheet

---

# Installation

1.  Install the plugin in WordPress.
2.  Run Composer:

```{=html}
<!-- -->
```

    composer install

3.  Ensure dependency exists:

```{=html}
<!-- -->
```

    phpoffice/phpspreadsheet

---

# License

GPL2+

---

# Author

Developed for **WP Barcode Manager**
