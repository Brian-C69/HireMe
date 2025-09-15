# REST API Endpoints

## GET /api/accounts/{id}
- **Function**: `AccountController::apiShow`
- **Description**: Retrieve account details by ID.

### Request Parameters
| Parameter | Type | Mandatory | Description |
|-----------|------|-----------|-------------|
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
    "email": "user@example.com",
    "role": "Candidate"
  }
}
```

## POST /api/accounts
- **Function**: `AccountController::apiCreate`
- **Description**: Create a new account.

### Request Body
| Field | Type | Mandatory | Description |
|-------|------|-----------|-------------|
| `email` | string | Yes | Valid email address |
| `password` | string | Yes | Minimum 8 characters |
| `role` | string | Yes | User role (e.g. `Candidate`, `Employer`) |

### Example Request
```json
{
  "email": "new@example.com",
  "password": "secret123",
  "role": "Employer"
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
    "email": "new@example.com",
    "role": "Employer"
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
