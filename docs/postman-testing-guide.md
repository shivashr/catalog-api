# Product API Project - Postman Testing Guide

This guide documents the current Laravel `api/v1` backend, how to organize it in Postman, and how to test each endpoint in a clean order.

Important note:
- All `api/v1` routes are protected by `auth:sanctum`.
- There is no dedicated `/api/v1/login` route in the current backend.
- To get a Bearer token for Postman, use the Artisan helper command:
  - `php artisan product:token owner@example.com local-product-api`

## Current API Modules Available

1. `00 Auth`
   - Current authenticated user endpoint
2. `01 Taxonomy`
   - Categories
   - Brands
   - Sub Categories
   - Category Brand Link
3. `02 Options For Product Form`
   - Active categories
   - Active brands for a category
   - Active sub-categories for a category
4. `03 Products`
   - Product CRUD
   - Product status update
5. `04 Future/New Modules`
   - Reserved for anything added later

## Postman Folder Structure

Use this exact collection structure:

```text
Product API Project
  00 Auth
  01 Taxonomy
    Categories
    Brands
    Sub Categories
    Category Brand Link
  02 Options For Product Form
  03 Products
  04 Future/New Modules
```

## Collection Variables

Set these variables at the collection level:

| Variable | Purpose | Example |
| --- | --- | --- |
| `base_url` | Root URL of the Laravel app | `http://127.0.0.1:8000` |
| `token` | Sanctum Bearer token | `1|xxxxxx` |
| `category_id` | Saved category ID | `12` |
| `brand_id` | Saved brand ID | `7` |
| `sub_category_id` | Saved sub-category ID | `18` |
| `product_id` | Saved product ID | `24` |

Recommended collection-level auth:
- Type: Bearer Token
- Token: `{{token}}`

Recommended collection-level header:
- `Accept: application/json`

For raw JSON requests also add:
- `Content-Type: application/json`

For `form-data` requests:
- Do not manually set `Content-Type`
- Let Postman generate `multipart/form-data`

## How To Test In Postman

1. Create a collection named `Product API Project`.
2. Add the folder structure shown above.
3. Add collection variables for `base_url`, `token`, and the ID variables.
4. Set Authorization once at the collection level:
   - Type: Bearer Token
   - Token: `{{token}}`
5. Add the `Accept: application/json` header at the collection level.
6. Use `{{base_url}}` in every request URL.
7. Choose body type:
   - `raw JSON` for normal create/update requests
   - `form-data` for file upload requests
   - `no body` for GET and DELETE requests
8. For file upload fields:
   - Use `images[]`
   - Set type to `File`
   - Add one or more files with the same key inside the same product create/update request
9. For nested product fields in `form-data`:
   - Use bracket notation like `seo[meta_title]`
   - Use `specifications[0][attribute]`
   - Use `warranty[warranty_unit]`
10. Save returned IDs from successful responses into variables.

Example Postman test script for saving an ID:

```javascript
const json = pm.response.json();
pm.collectionVariables.set('category_id', json.data.id);
```

## Standard Headers For Every API Request

Use these headers on every request:

- `Accept: application/json`
- `Authorization: Bearer {{token}}`

If you send `raw JSON`, also include:

- `Content-Type: application/json`

If you send `form-data`, Postman will set the content type automatically.

## 00 Auth

### GET `{{base_url}}/api/v1/user`

- Postman folder: `Product API Project > 00 Auth`
- Method: `GET`
- Auth required: Yes
- Required role: Any authenticated user with a valid Sanctum token
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: None

Expected success response:

```json
{
  "id": 1,
  "tenant_id": 1,
  "name": "Owner User",
  "email": "owner@example.com",
  "role": "owner",
  "status": "active",
  "created_at": "2026-05-22T00:00:00.000000Z",
  "updated_at": "2026-05-22T00:00:00.000000Z"
}
```

Expected validation/auth error response:

```json
{
  "message": "Unauthenticated."
}
```

Notes:
- This endpoint returns the current authenticated user directly, not inside a `data` wrapper.
- There is no login endpoint in `api/v1`; use the Artisan token command before testing.

## 01 Taxonomy

### Categories

#### GET `{{base_url}}/api/v1/categories`

- Postman folder: `Product API Project > 01 Taxonomy > Categories`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: None

Expected success response:

```json
{
  "data": [
    {
      "id": 12,
      "tenant_id": 1,
      "name": "Electronics",
      "slug": "electronics",
      "status": "active",
      "brands": [],
      "sub_categories": [],
      "created_at": "2026-05-22T00:00:00.000000Z",
      "updated_at": "2026-05-22T00:00:00.000000Z"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "per_page": 15
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "This action is unauthorized."
}
```

#### POST `{{base_url}}/api/v1/categories`

- Postman folder: `Product API Project > 01 Taxonomy > Categories`
- Method: `POST`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "name": "Electronics",
  "slug": "electronics",
  "status": "active"
}
```

- Collection variables needed: `category_id` after success

Expected success response:

```json
{
  "data": {
    "id": 12,
    "tenant_id": 1,
    "name": "Electronics",
    "slug": "electronics",
    "status": "active",
    "brands": [],
    "sub_categories": [],
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

#### GET `{{base_url}}/api/v1/categories/{{category_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Categories`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `category_id`

Expected success response:

```json
{
  "data": {
    "id": 12,
    "tenant_id": 1,
    "name": "Electronics",
    "slug": "electronics",
    "status": "active",
    "brands": [
      {
        "id": 7,
        "name": "Acme",
        "slug": "acme",
        "status": "active"
      }
    ],
    "sub_categories": [
      {
        "id": 18,
        "category_id": 12,
        "name": "Headphones",
        "slug": "headphones",
        "status": "active"
      }
    ],
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "You do not have permission to access this category."
}
```

#### PUT/PATCH `{{base_url}}/api/v1/categories/{{category_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Categories`
- Method: `PUT` or `PATCH`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "name": "Consumer Electronics",
  "slug": "consumer-electronics",
  "status": "active"
}
```

- Collection variables needed: `category_id`

Expected success response:

```json
{
  "data": {
    "id": 12,
    "tenant_id": 1,
    "name": "Consumer Electronics",
    "slug": "consumer-electronics",
    "status": "active",
    "brands": [],
    "sub_categories": [],
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "slug": [
      "The slug has already been taken."
    ]
  }
}
```

#### DELETE `{{base_url}}/api/v1/categories/{{category_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Categories`
- Method: `DELETE`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `category_id`

Expected success response:

```json
null
```

Expected validation/business-rule error response:

```json
{
  "message": "Cannot delete a category that is already used by products."
}
```

### Category Brand Link

#### PUT `{{base_url}}/api/v1/categories/{{category_id}}/brands`

- Postman folder: `Product API Project > 01 Taxonomy > Category Brand Link`
- Method: `PUT`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "brand_ids": [7, 8]
}
```

- Collection variables needed: `category_id`, `brand_id`

Expected success response:

```json
{
  "data": {
    "id": 12,
    "tenant_id": 1,
    "name": "Electronics",
    "slug": "electronics",
    "status": "active",
    "brands": [
      {
        "id": 7,
        "name": "Acme",
        "slug": "acme",
        "status": "active"
      }
    ],
    "sub_categories": [],
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "brand_ids": [
      "The brand ids field is required."
    ]
  }
}
```

### Brands

#### GET `{{base_url}}/api/v1/brands`

- Postman folder: `Product API Project > 01 Taxonomy > Brands`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: None

Expected success response:

```json
{
  "data": [
    {
      "id": 7,
      "tenant_id": 1,
      "name": "Acme",
      "slug": "acme",
      "status": "active",
      "categories": [],
      "created_at": "2026-05-22T00:00:00.000000Z",
      "updated_at": "2026-05-22T00:00:00.000000Z"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "per_page": 15
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "This action is unauthorized."
}
```

#### POST `{{base_url}}/api/v1/brands`

- Postman folder: `Product API Project > 01 Taxonomy > Brands`
- Method: `POST`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "name": "Acme",
  "slug": "acme",
  "status": "active"
}
```

- Collection variables needed: `brand_id` after success

Expected success response:

```json
{
  "data": {
    "id": 7,
    "tenant_id": 1,
    "name": "Acme",
    "slug": "acme",
    "status": "active",
    "categories": [],
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

#### GET `{{base_url}}/api/v1/brands/{{brand_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Brands`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `brand_id`

Expected success response:

```json
{
  "data": {
    "id": 7,
    "tenant_id": 1,
    "name": "Acme",
    "slug": "acme",
    "status": "active",
    "categories": [
      {
        "id": 12,
        "name": "Electronics",
        "slug": "electronics",
        "status": "active"
      }
    ],
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "You do not have permission to access this brand."
}
```

#### PUT/PATCH `{{base_url}}/api/v1/brands/{{brand_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Brands`
- Method: `PUT` or `PATCH`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "name": "Acme Pro",
  "slug": "acme-pro",
  "status": "active"
}
```

- Collection variables needed: `brand_id`

Expected success response:

```json
{
  "data": {
    "id": 7,
    "tenant_id": 1,
    "name": "Acme Pro",
    "slug": "acme-pro",
    "status": "active",
    "categories": [],
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "slug": [
      "The slug has already been taken."
    ]
  }
}
```

#### DELETE `{{base_url}}/api/v1/brands/{{brand_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Brands`
- Method: `DELETE`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `brand_id`

Expected success response:

```json
null
```

Expected validation/business-rule error response:

```json
{
  "message": "Cannot delete a brand that is already used by products."
}
```

### Sub Categories

#### GET `{{base_url}}/api/v1/sub-categories`

- Postman folder: `Product API Project > 01 Taxonomy > Sub Categories`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: None

Expected success response:

```json
{
  "data": [
    {
      "id": 18,
      "tenant_id": 1,
      "category_id": 12,
      "name": "Headphones",
      "slug": "headphones",
      "status": "active",
      "category": {
        "id": 12,
        "name": "Electronics",
        "slug": "electronics",
        "status": "active"
      },
      "created_at": "2026-05-22T00:00:00.000000Z",
      "updated_at": "2026-05-22T00:00:00.000000Z"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "per_page": 15
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "This action is unauthorized."
}
```

#### POST `{{base_url}}/api/v1/sub-categories`

- Postman folder: `Product API Project > 01 Taxonomy > Sub Categories`
- Method: `POST`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "category_id": 12,
  "name": "Headphones",
  "slug": "headphones",
  "status": "active"
}
```

- Collection variables needed: `sub_category_id` after success

Expected success response:

```json
{
  "data": {
    "id": 18,
    "tenant_id": 1,
    "category_id": 12,
    "name": "Headphones",
    "slug": "headphones",
    "status": "active",
    "category": {
      "id": 12,
      "name": "Electronics",
      "slug": "electronics",
      "status": "active"
    },
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "category_id": [
      "The category id field is required."
    ]
  }
}
```

#### GET `{{base_url}}/api/v1/sub-categories/{{sub_category_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Sub Categories`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `sub_category_id`

Expected success response:

```json
{
  "data": {
    "id": 18,
    "tenant_id": 1,
    "category_id": 12,
    "name": "Headphones",
    "slug": "headphones",
    "status": "active",
    "category": {
      "id": 12,
      "name": "Electronics",
      "slug": "electronics",
      "status": "active"
    },
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "You do not have permission to access this sub-category."
}
```

#### PUT/PATCH `{{base_url}}/api/v1/sub-categories/{{sub_category_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Sub Categories`
- Method: `PUT` or `PATCH`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "category_id": 12,
  "name": "Wireless Headphones",
  "slug": "wireless-headphones",
  "status": "active"
}
```

- Collection variables needed: `sub_category_id`

Expected success response:

```json
{
  "data": {
    "id": 18,
    "tenant_id": 1,
    "category_id": 12,
    "name": "Wireless Headphones",
    "slug": "wireless-headphones",
    "status": "active",
    "category": {
      "id": 12,
      "name": "Electronics",
      "slug": "electronics",
      "status": "active"
    },
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "slug": [
      "The slug has already been taken."
    ]
  }
}
```

#### DELETE `{{base_url}}/api/v1/sub-categories/{{sub_category_id}}`

- Postman folder: `Product API Project > 01 Taxonomy > Sub Categories`
- Method: `DELETE`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `sub_category_id`

Expected success response:

```json
null
```

Expected validation/business-rule error response:

```json
{
  "message": "Cannot delete a sub-category that is already used by products."
}
```

## 02 Options For Product Form

These are helper endpoints for building the product create/edit form.

### GET `{{base_url}}/api/v1/options/categories/active`

- Postman folder: `Product API Project > 02 Options For Product Form`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: None

Expected success response:

```json
{
  "data": [
    {
      "id": 12,
      "name": "Electronics",
      "slug": "electronics"
    }
  ]
}
```

Expected validation/auth error response:

```json
{
  "message": "This action is unauthorized."
}
```

### GET `{{base_url}}/api/v1/options/categories/{{category_id}}/brands/active`

- Postman folder: `Product API Project > 02 Options For Product Form`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `category_id`

Expected success response:

```json
{
  "data": [
    {
      "id": 7,
      "name": "Acme",
      "slug": "acme"
    }
  ]
}
```

Expected validation/auth error response:

```json
{
  "message": "You do not have permission to access this category."
}
```

### GET `{{base_url}}/api/v1/options/categories/{{category_id}}/sub-categories/active`

- Postman folder: `Product API Project > 02 Options For Product Form`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `category_id`

Expected success response:

```json
{
  "data": [
    {
      "id": 18,
      "name": "Headphones",
      "slug": "headphones"
    }
  ]
}
```

Expected validation/auth error response:

```json
{
  "message": "You do not have permission to access this category."
}
```

## 03 Products

### GET `{{base_url}}/api/v1/products`

- Postman folder: `Product API Project > 03 Products`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: None
- Optional query params:
  - `per_page=15`
  - Maximum allowed is `100`

Expected success response:

```json
{
  "data": [
    {
      "id": 24,
      "tenant_id": 1,
      "category_id": 12,
      "brand_id": 7,
      "sub_category_id": 18,
      "name": "Trail Running Shoe",
      "slug": "trail-running-shoe",
      "model_number": null,
      "condition": "new",
      "description": "Balanced cushion and grip for mixed surfaces.",
      "tags": [],
      "selling_price": "89.99",
      "mrp_price": null,
      "stock_quantity": 20,
      "low_stock_alert": null,
      "sku": "TRAIL-001",
      "barcode": null,
      "cost_price": null,
      "tax_rate": null,
      "weight": null,
      "length": null,
      "width": null,
      "height": null,
      "shipping_class": null,
      "free_shipping": false,
      "status": "active"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "per_page": 15
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "This action is unauthorized."
}
```

### POST `{{base_url}}/api/v1/products`

- Postman folder: `Product API Project > 03 Products`
- Method: `POST`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type:
  - `raw JSON` for draft-only or non-file payloads
  - `form-data` when uploading images
- Collection variables needed:
  - `category_id`
  - `brand_id`
  - `sub_category_id`
  - `product_id` after success

Raw JSON request body example for a draft product:

```json
{
  "status": "draft"
}
```

Raw JSON request body example for a full active product:

```json
{
  "category_id": 12,
  "brand_id": 7,
  "sub_category_id": 18,
  "name": "Noise Cancelling Headphones",
  "slug": "noise-cancelling-headphones",
  "model_number": "NC-500",
  "condition": "new",
  "description": "Over-ear wireless ANC headphones.",
  "tags": "audio, wireless, anc",
  "selling_price": 199.99,
  "mrp_price": 249.99,
  "stock_quantity": 50,
  "low_stock_alert": 10,
  "sku": "NC-500",
  "barcode": "123456789012",
  "cost_price": 130,
  "tax_rate": 13,
  "weight": 0.35,
  "length": 18.5,
  "width": 16.2,
  "height": 8.4,
  "shipping_class": "Standard",
  "free_shipping": true,
  "status": "active",
  "seo": {
    "meta_title": "Noise Cancelling Headphones",
    "meta_description": "Premium wireless ANC headphones."
  },
  "variants": {
    "size": ["One Size"],
    "color": ["Black", "Blue"],
    "material": ["Plastic", "Metal"]
  },
  "specifications": [
    {
      "attribute": "Battery Life",
      "value": "40 hours",
      "sort_order": 0
    },
    {
      "attribute": "Bluetooth",
      "value": "5.3",
      "sort_order": 1
    }
  ],
  "warranty": {
    "warranty_duration": 12,
    "warranty_unit": "months",
    "warranty_by": "seller",
    "return_window": "7 Days",
    "return_type": "exchange_only"
  }
}
```

Form-data example for an active product with images:

| Key | Type | Value |
| --- | --- | --- |
| `category_id` | Text | `12` |
| `brand_id` | Text | `7` |
| `sub_category_id` | Text | `18` |
| `name` | Text | `Noise Cancelling Headphones` |
| `slug` | Text | `noise-cancelling-headphones` |
| `model_number` | Text | `NC-500` |
| `condition` | Text | `new` |
| `description` | Text | `Over-ear wireless ANC headphones.` |
| `tags` | Text | `audio, wireless, anc` |
| `selling_price` | Text | `199.99` |
| `mrp_price` | Text | `249.99` |
| `stock_quantity` | Text | `50` |
| `low_stock_alert` | Text | `10` |
| `sku` | Text | `NC-500` |
| `barcode` | Text | `123456789012` |
| `cost_price` | Text | `130` |
| `tax_rate` | Text | `13` |
| `weight` | Text | `0.35` |
| `length` | Text | `18.5` |
| `width` | Text | `16.2` |
| `height` | Text | `8.4` |
| `shipping_class` | Text | `Standard` |
| `free_shipping` | Text | `1` |
| `status` | Text | `active` |
| `seo[meta_title]` | Text | `Noise Cancelling Headphones` |
| `seo[meta_description]` | Text | `Premium wireless ANC headphones.` |
| `variants[size][]` | Text | `One Size` |
| `variants[color][]` | Text | `Black` |
| `variants[color][]` | Text | `Blue` |
| `variants[material][]` | Text | `Plastic` |
| `variants[material][]` | Text | `Metal` |
| `specifications[0][attribute]` | Text | `Battery Life` |
| `specifications[0][value]` | Text | `40 hours` |
| `specifications[0][sort_order]` | Text | `0` |
| `specifications[1][attribute]` | Text | `Bluetooth` |
| `specifications[1][value]` | Text | `5.3` |
| `specifications[1][sort_order]` | Text | `1` |
| `warranty[warranty_duration]` | Text | `12` |
| `warranty[warranty_unit]` | Text | `months` |
| `warranty[warranty_by]` | Text | `seller` |
| `warranty[return_window]` | Text | `7 Days` |
| `warranty[return_type]` | Text | `exchange_only` |
| `images[]` | File | `headphones.jpg` |
| `images[]` | File | `headphones-side.jpg` |

Expected success response:

```json
{
  "data": {
    "id": 24,
    "tenant_id": 1,
    "category_id": 12,
    "brand_id": 7,
    "sub_category_id": 18,
    "name": "Noise Cancelling Headphones",
    "slug": "noise-cancelling-headphones",
    "model_number": "NC-500",
    "condition": "new",
    "description": "Over-ear wireless ANC headphones.",
    "tags": [
      "audio",
      "wireless",
      "anc"
    ],
    "selling_price": "199.99",
    "mrp_price": "249.99",
    "stock_quantity": 50,
    "low_stock_alert": 10,
    "sku": "NC-500",
    "barcode": "123456789012",
    "cost_price": "130.00",
    "tax_rate": "13.00",
    "weight": "0.35",
    "length": "18.50",
    "width": "16.20",
    "height": "8.40",
    "shipping_class": "Standard",
    "free_shipping": true,
    "status": "active",
    "category": {
      "id": 12,
      "name": "Electronics",
      "slug": "electronics",
      "status": "active"
    },
    "brand": {
      "id": 7,
      "name": "Acme",
      "slug": "acme",
      "status": "active"
    },
    "sub_category": {
      "id": 18,
      "category_id": 12,
      "name": "Headphones",
      "slug": "headphones",
      "status": "active"
    },
    "images": [
      {
        "id": 55,
        "image_path": "products/headphones.jpg",
        "url": "https://example.com/storage/products/headphones.jpg",
        "alt_text": null,
        "sort_order": 0,
        "is_primary": true
      }
    ],
    "variants": {
      "size": [
        "One Size"
      ],
      "color": [
        "Black",
        "Blue"
      ],
      "material": [
        "Plastic",
        "Metal"
      ]
    },
    "specifications": [
      {
        "id": 1,
        "attribute": "Battery Life",
        "value": "40 hours",
        "sort_order": 0
      }
    ],
    "warranty": {
      "warranty_duration": 12,
      "warranty_unit": "months",
      "warranty_by": "seller",
      "return_window": "7 Days",
      "return_type": "exchange_only"
    },
    "seo": {
      "meta_title": "Noise Cancelling Headphones",
      "meta_description": "Premium wireless ANC headphones."
    },
    "created_at": "2026-05-22T00:00:00.000000Z",
    "updated_at": "2026-05-22T00:00:00.000000Z"
  }
}
```

Expected validation error response for an active product with missing publish data:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "category_id": [
      "A category is required to publish an active product."
    ],
    "brand_id": [
      "A brand is required to publish an active product."
    ],
    "sub_category_id": [
      "A sub-category is required to publish an active product."
    ],
    "images": [
      "At least one product image is required to publish an active product."
    ]
  }
}
```

Expected business-rule error response examples:

```json
{
  "message": "The selected brand is not linked to the selected category."
}
```

```json
{
  "message": "The selected sub-category does not belong to the selected category."
}
```

### GET `{{base_url}}/api/v1/products/{{product_id}}`

- Postman folder: `Product API Project > 03 Products`
- Method: `GET`
- Auth required: Yes
- Required role: `owner`, `admin`, or `staff`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `product_id`

Expected success response:

```json
{
  "data": {
    "id": 24,
    "tenant_id": 1,
    "category_id": 12,
    "brand_id": 7,
    "sub_category_id": 18,
    "name": "Trail Running Shoe",
    "slug": "trail-running-shoe",
    "condition": "new",
    "description": "Balanced cushion and grip for mixed surfaces.",
    "status": "active",
    "images": [],
    "variants": {
      "size": [],
      "color": [],
      "material": []
    },
    "specifications": [],
    "warranty": null,
    "seo": {
      "meta_title": null,
      "meta_description": null
    }
  }
}
```

Expected validation/auth error response:

```json
{
  "message": "You do not have permission to access this product."
}
```

### PUT/PATCH `{{base_url}}/api/v1/products/{{product_id}}`

- Postman folder: `Product API Project > 03 Products`
- Method: `PUT` or `PATCH`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type:
  - `raw JSON` for normal updates
  - `form-data` if you want to upload additional images in the same request
- Collection variables needed: `product_id`

Raw JSON request body example:

```json
{
  "name": "Updated CRUD Product",
  "sku": "FULL-CRUD-002",
  "selling_price": 249.99
}
```

Form-data example for updating with images:

| Key | Type | Value |
| --- | --- | --- |
| `name` | Text | `Updated CRUD Product` |
| `sku` | Text | `FULL-CRUD-002` |
| `selling_price` | Text | `249.99` |
| `images[]` | File | `gallery.png` |

Expected success response:

```json
{
  "data": {
    "id": 24,
    "tenant_id": 1,
    "name": "Updated CRUD Product",
    "sku": "FULL-CRUD-002",
    "selling_price": "249.99",
    "status": "active"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "sku": [
      "The sku has already been taken."
    ]
  }
}
```

### PATCH `{{base_url}}/api/v1/products/{{product_id}}/status`

- Postman folder: `Product API Project > 03 Products`
- Method: `PATCH`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `raw JSON`
- Request body example:

```json
{
  "status": "draft"
}
```

- Collection variables needed: `product_id`

Expected success response:

```json
{
  "data": {
    "id": 24,
    "status": "draft"
  }
}
```

Expected validation error response:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

### DELETE `{{base_url}}/api/v1/products/{{product_id}}`

- Postman folder: `Product API Project > 03 Products`
- Method: `DELETE`
- Auth required: Yes
- Required role: `owner` or `admin`
- Headers:
  - `Accept: application/json`
  - `Authorization: Bearer {{token}}`
- Body type: `no body`
- Request body example: None
- Collection variables needed: `product_id`

Expected success response:

```json
null
```

Expected validation/error response:

```json
{
  "message": "You do not have permission to modify this product."
}
```

## 04 Future/New Modules

Use this section for any new API module added later.

When a new module is created:
- Add a new folder under `Product API Project`
- Add a new section in this guide
- Document every endpoint with method, auth, body, request example, success response, and validation response

## Recommended Testing Order

Use this order when demonstrating the API in Postman:

1. Login/get token
   - There is no API login route in `api/v1`
   - Generate a token with `php artisan product:token owner@example.com local-product-api`
2. Create Category
3. Create Brand
4. Link Brand to Category
5. Create Sub-category
6. Fetch option APIs
7. Create Draft Product
8. Create Active Product with image
9. List Products
10. Show Product
11. Update Product
12. Update Product Status
13. Delete Product
14. Test invalid brand/category relation
15. Test invalid sub-category/category relation

## Missing APIs Or Unclear Fields Found

- No dedicated `login`, `logout`, or `refresh token` endpoint exists under `api/v1`.
- Auth testing depends on Sanctum token generation via the Artisan command.
- `product` payload accepts both grouped variant objects and list-style variant arrays.
- `tags` accepts either a comma-separated string or an array.
- `images[]` must be sent as file fields in `form-data` inside the product create/update request.
- List endpoints are paginated and respect `per_page`, capped at `100`.

## Notes For Demoing To Sir

- The API is organized around four main areas: auth, taxonomy, product-form options, and products.
- Taxonomy routes are protected by role checks:
  - list/view: `owner`, `admin`, `staff`
  - create/update/delete/linking: `owner`, `admin`
- Product publishing requires a complete payload plus at least one image when status is `active`.
- Validation and business-rule failures are already covered by feature tests in `tests/Feature`.
