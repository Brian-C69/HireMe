# REST API Endpoints

## GET /api/accounts/{type}/{id}
- **Function**: `AccountController::apiShow`
- **Description**: Retrieve account details by ID for a given account type.

### Request Parameters
| Parameter | Type | Mandatory | Description |
|-----------|------|-----------|-------------|
| `type` | string (path) | Yes | Account type (`admin`, `candidate`, `employer`, `recruiter`) |
| `id` | integer (path) | Yes | Account identifier |

### Response Fields
| Field | Type | Mandatory | Description |
|-------|------|-----------|-------------|
| `status` | string | Yes | `success` or `error` |
| `message` | string | Yes | Human readable status message |
| `data` | object \| null | Yes | Account data on success or `null` on error |

### Example Response
```json
{
  "status": "success",
  "message": "Account retrieved",
  "data": {
    "id": 123,
    "email": "user@example.com"
  }
}
```

## POST /api/accounts/{type}
- **Function**: `AccountController::apiCreate`
- **Description**: Create a new account for a given type.

### Request Body
| Field | Type | Mandatory | Description |
|-------|------|-----------|-------------|
| `email` | string | Yes | Valid email address |
| `password` | string | Yes | Minimum 8 characters |

### Example Request
```json
{
  "email": "new@example.com",
  "password": "secret123"
}
```

### Response Fields
| Field | Type | Mandatory | Description |
|-------|------|-----------|-------------|
| `status` | string | Yes | `success` or `error` |
| `message` | string | Yes | Human readable status message |
| `data` | object \| null | Yes | Created account data or validation errors |

### Example Success Response
```json
{
  "status": "success",
  "message": "Account created",
  "data": {
    "id": 124,
    "email": "new@example.com"
  }
}
```

### Example Error Response
```json
{
  "status": "error",
  "message": "Validation failed",
  "data": {
    "email": "Valid email required"
  }
}
```
