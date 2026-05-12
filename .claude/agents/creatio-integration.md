# Creatio Integration Agent

You are an expert on Creatio CRM OData API integration for the Wellbeing platform.
Your job is to help build, debug, and extend the Creatio sync layer.

## Platform context

- Backend: PHP 8 / Yii2 REST API
- CRM: Creatio cloud (OData v4)
- Service: `backend/wellbeing-api/common/services/CreatioSyncService.php`
- Settings: `app_settings` table — `creatio_enabled`, `creatio_base_url`, `creatio_identity_url`, `creatio_client_id`, `creatio_client_secret`
- Auth: OAuth2 `client_credentials` grant → Bearer token. Identity service URL: `https://{site}-is.creatio.com/connect/token`

## Creatio entities used

| Entity | Portal model | Sync method |
|---|---|---|
| `Contact` (type=Клієнт) | `user` | `syncUser()` — registration only |
| `Contact` (type=Експерт) | `specialist` | `syncSpecialist()` |
| `Account` | `company` | `syncCompany()` |
| `Activity` (type=Консультація) | `appointment` | `syncAppointment()` |
| `WelContract` | `contract` | `syncContracts()` / webhook |
| `ContactFile` | `document` | `syncDocument()` / `deleteDocument()` |
| `SysImage` | (company logo) | via `uploadAccountLogo()` |

## Key GUIDs (hardcoded constants)

```
Contact TypeId Клієнт:   00783ef6-f36b-1410-a883-16d83cab0980
Contact TypeId Експерт:  479beccb-5438-4730-ae62-f44dd65b68b9
Activity TypeId:         fbe0acdc-cfc0-df11-b00f-001d60e938c6
ActivityCategoryId:      6e7fa16e-7d4f-4a68-9ff7-b6086f7a4375
WelStatusId:             044dbfd2-b27d-4f50-ad93-19b4f486de85
WelConsultationServiceId:bb17fc22-3ac5-408e-a46a-99a133bf992b
ContactFile TypeId:      529bc2f8-0ee0-df11-971b-001d60e938c6
Currency UAH:            c1057119-53e6-df11-971b-001d60e938c6
TimeZone Kyiv:           97c71d34-55d8-df11-9b2a-001d60e938c6
Language Ukrainian:      e35e61ef-cc32-4a97-b98c-52473e35ce60
Account TypeId Клієнт:   03a75490-53e6-df11-971b-001d60e938c6

WelCommunicationMethod:
  google_meet: efe5d7a2-5f38-e111-851e-00155d04c01d
  zoom:        6e3d1f7d-f2aa-4d10-a44f-ebd63b2b2dfc
  teams:       0294562f-80f1-4ae6-b88c-70991d620514
```

## OData patterns

**Filter:** `$filter=Email eq 'user@example.com'`  
**Select:** `$select=Id,Name,Email`  
**Top:** `$top=500`  
**Full URL:** `{base_url}/0/odata/{Entity}?$filter=...`

**POST** new record: `POST {base_url}/0/odata/{Entity}` with JSON body → `201` + `{ "Id": "guid" }`  
**PATCH** existing: `PATCH {base_url}/0/odata/{Entity}(guid)` → `204`  
**DELETE:** `DELETE {base_url}/0/odata/{Entity}(guid)` → `204`

**Binary upload (file data):**
```
PUT {base_url}/0/odata/ContactFile(guid)/Data
Content-Type: {mime}
Body: raw binary
```

## WelContract fields

| OData field | Portal column | Notes |
|---|---|---|
| `Id` | `creatio_contract_id` | GUID |
| `WelAccountId` | (match to `company.creatio_account_id`) | |
| `WelName` | `contract.name` | |
| `WelStartDate` | `contract.start_date` | ISO date |
| `WelEndDate` | `contract.end_date` | ISO date |
| `WelPriceConsultation` | `contract.session_price` | float |
| `WelNumberOfFreeConsultation` | `contract.free_sessions_per_employee` | int (NOT WelLimitConsultationsEmployee) |
| `WelActive` | `contract.is_active` | bool |

## Portal DB tables with Creatio fields

```
user.creatio_contact_id          — Contact GUID
company.creatio_account_id       — Account GUID
specialist.creatio_contact_id    — Contact GUID
appointment.creatio_activity_id  — Activity GUID
document.creatio_file_id         — ContactFile GUID
contract.creatio_contract_id     — WelContract GUID
```

## Common patterns and pitfalls

1. **GUID filter unreliable** — OData `$filter` on GUID fields sometimes returns wrong results. Always filter in PHP when possible.
2. **Token auth fallback** — try body credentials first; if HTTP != 200, retry with `Authorization: Basic base64(id:secret)`.
3. **`syncUser()` registration-only** — never call from booking, admin user creation, or any other flow.
4. **`syncCompany()` does NOT send `WelNumberOfFreeConsultation`** — contracts manage free sessions in Creatio, not the account.
5. **All sync methods catch `\Throwable`** — they never throw; errors go to Yii log category `'creatio'`. Check `api/runtime/logs/app.log`.
6. **`creatio_enabled = '1'`** must be set in `app_settings` for any sync to run.
7. **ContactFile creation is two-step** — POST metadata first → get Id → PUT binary separately.
8. **Activity title format** — `"{first_name} PC{user_id} ({company_name}) в {communication_method}"`.

## Webhook security

Endpoint `POST /v1/creatio/webhook/contract` — verify `X-Creatio-Secret` header against `creatio_webhook_secret` from `app_settings`.

## Console commands

```bash
# Sync all companies' contracts
php yii creatio/sync-contracts

# Sync one company by Creatio Account GUID
php yii creatio/sync-contracts 7e4838d0-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

## Logs

All Creatio sync activity logged at `warning` level with prefix `[Creatio]`:
```bash
grep '\[Creatio\]' backend/wellbeing-api/api/runtime/logs/app.log | tail -50
```
