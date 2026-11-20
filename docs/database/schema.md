# Schema database — P01.01

Tài liệu kỹ thuật schema theo `../specification.md` §§6.1–6.5. Triển khai bằng Laravel13/PHP8.4, MySQL8.4. Đây là tài liệu cấu trúc để tiếp tục phát triển; kết quả lệnh và trạng thái task nằm trong `../ai-progress.md`.

## Phạm vi và quy ước

- **34 bảng miền +6 bảng hạ tầng =40 bảng ứng dụng/hạ tầng**; thêm bảng Laravel `migrations` là **41 bảng thực tế**. Không có `password_reset_tokens`.
- Các bảng miền có tổng **483 cột**, gồm4 cột generated phục vụ unique có điều kiện; **70 FK**, **79 index/unique khai báo trong dictionary** (không đếm PK hoặc index FK tự sinh), **110 CHECK được enforce**.
- Mọi PK miền: BIGINT UNSIGNED AUTO_INCREMENT. Bảng InnoDB, utf8mb4_unicode_ci. VARCHAR enum được CHECK bằng so sánh binary để không chấp nhận sai hoa/thường. Giá bán/thu/hoàn nguyên VND BIGINT; chi phí bình quân DECIMAL(20,4), delta ledger signed.
- Timestamp miền: DATETIME(6), NOT NULL khi dictionary yêu cầu; nullable chỉ khi ghi rõ. Không dùng `$table->timestamps()` mặc định nullable/TIMESTAMP thay cho contract. UTC do config PHP/MySQL; chuyển múi giờ nghiệp vụ khi hiển thị.
- FK ON DELETE RESTRICT và ON UPDATE RESTRICT; ngoại lệ duy nhất là `cart_items.cart_id` ON DELETE CASCADE. Ba migration hạ tầng P00 được giữ nguyên, không tạo lại sessions/cache/jobs và không đổi schema framework thành schema nghiệp vụ.
- FK `inventory_movements.reference_id` và `outbox_events.aggregate_id` là đa hình theo alias, **không có FK SQL giả**; service ở phase sau chịu trách nhiệm whitelist/owner/tồn tại.
- Negative unsigned bucket bị MySQL từ chối theo kiểu dữ liệu; quan hệ reserved<=stock và phương trình tiền/kho được CHECK bảo vệ thêm. Không dùng unsigned cho delta có thể âm.

## Thứ tự migration và rollback

| Nhóm | Bảng theo thứ tự tạo |
|---|---|
| P00 hạ tầng | sessions; cache/cache_locks; jobs/job_batches/failed_jobs |
| 1 — identity/locations | users → roles → user_roles → password_reset_challenges → administrative_units → addresses |
| 2 — catalog/inventory | categories → products → product_colors → product_images → product_variants → suppliers → stock_imports → stock_import_items → inventory_movements |
| 3 — cart/orders | carts → cart_items → coupons → orders → order_items → order_item_units → order_status_histories → coupon_redemptions |
| 4 — payment/shipment/review | payments → payment_events → shipments → shipment_return_items → reviews |
| 5 — RMA/refund/ledger | after_sales_requests → after_sales_histories → after_sales_attachments → refunds → sales_ledger_entries → outbox_events |
| 6 — constraints |4 generated columns/unique indexes, sau đó110 CHECK |

Rollback nhóm6 tháo CHECK trước, rồi unique/helper columns. Năm nhóm bảng tháo theo chiều ngược FK. Không tắt foreign_key_checks để che thứ tự sai. Tên index quá dài được đặt ngắn rõ ràng để tuân giới hạn64 ký tự MySQL; danh sách cột và tính unique không thay đổi.

Local nâng cấp: `php artisan migrate --force --no-interaction` (chỉ thêm sáu migration mới). Không reset/fresh development. Các lệnh reset/rollback dựng trắng trong MigrationLifecycleTest chỉ chạy sau guard xác minh MySQL `cena_store_test` và user test, không dùng database phát triển.

## Generated helpers — chỉ để database quản lý

| Bảng.cột | Giá trị khi cần giữ unique slot | Khi không giữ slot |
|---|---|---|
| addresses.default_owner_id | user_id khi is_default=1 và deleted_at IS NULL | NULL |
| product_images.thumbnail_color_id | product_color_id khi is_thumbnail=1 | NULL |
| payments.pending_order_id | order_id khi status=PENDING | NULL |
| after_sales_requests.active_unit_id | order_item_unit_id khi NEW/APPROVED/RECEIVED/PROCESSING/AWAITING_REFUND/READY_FOR_PICKUP | NULL |

Các cột trên là BIGINT UNSIGNED nullable STORED GENERATED và có unique index. Nhiều NULL được phép, nên lịch sử/địa chỉ không mặc định/ảnh không thumbnail vẫn cùng tồn tại. Models ở P01.02 phải loại helper khỏi fillable và payload, không gán giá trị trực tiếp.

## Phân chia trách nhiệm database / application

Database bảo vệ kiểu/null/default/PK/FK/unique/CHECK cục bộ một dòng. Application vẫn phải thực thi: cùng owner qua nhiều cấp, ward thuộc province và cùng version, category không cycle/tối đa hai cấp, target variant cùng color/khác size, palette12 màu và canonical size, tối thiểu một thumbnail khi public, quota coupon/stock dưới row lock, state transition hợp lệ, tổng unit/line/order xuyên bảng, snapshot/append-only và idempotency xuyên transaction. Không thể suy luận các nghiệp vụ đó đã hoàn thành chỉ từ việc có schema.

`stock_import_items.unit_cost_amount=0` vẫn được phép ở schema vì đặc tả có nhập tặng có duyệt; service nhập kho phải kiểm reason/quyền. Không thêm CHECK >0 làm mất trường hợp hợp lệ này. Order total/unit net=0 cũng có thể hợp lệ sau coupon100%; không tạo refund0 theo quy tắc RefundService sau này.

Fixture kiểm schema ở `tests/Fixtures/schema-contract.json` là contract cố định trích dictionary, được so với information_schema thực tế, không đọc migration để kết luận đúng. Test cấu trúc tạo raw SQL fixtures tối thiểu vì models/factories đầy đủ còn ở P01.02/P01.04; đó không phải dữ liệu demo vận hành.

## Luồng coupon, thanh toán mô phỏng và minh chứng giao hàng

Migration `2026_11_05_000001_add_demo_prepaid_and_shipment_delivery_proofs` bổ sung `PREPAID_DEMO` vào các CHECK phương thức thanh toán hiện hữu và tạo `shipment_delivery_proofs`. Bảng minh chứng giữ đường dẫn trên private disk, MIME, kích thước, SHA-256 duy nhất và người tải lên; mỗi shipment chỉ có một minh chứng. Ảnh chỉ được ghi khi giao thành công đơn COD. Quyền đọc được cung cấp qua route back-office có middleware nhân viên, không trả đường dẫn file cho phía khách.

Coupon giữ quota bằng cách khóa dòng `coupons` trong cùng transaction tạo đơn và ghi `coupon_redemptions` trạng thái `RESERVED`. Đơn giao thành công chuyển thành `CONSUMED`; hủy trước giao hoặc giao thất bại chuyển thành `RELEASED`. Giá sau giảm được lưu vào snapshot đơn, dòng hàng, đơn vị hàng và lần thanh toán để tính doanh thu/hoàn tiền nhất quán. Thanh toán PREPAID_DEMO chỉ ghi nhận tiền đã thu mô phỏng; phê duyệt hoàn tiền không ghi nhận giao dịch chuyển tiền thật.

## Dictionary triển khai

Giá trị “không” trong nullable nghĩa NOT NULL. Default “—” nghĩa không khai báo default (SQL metadata NULL), không có nghĩa cột cho phép NULL. Ý nghĩa nghiệp vụ/snapshot/owner chi tiết giữ nguyên trong DB-01..DB-34 của specification.

### DB-01. `users`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `full_name` | `varchar(100)` | không | — |  |
| `email` | `varchar(255)` | không | — |  |
| `phone` | `varchar(20)` | có | — |  |
| `password` | `varchar(255)` | không | — |  |
| `avatar_path` | `varchar(500)` | có | — |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `last_login_at` | `datetime(6)` | có | — |  |
| `email_verified_at` | `datetime(6)` | có | — |  |
| `auth_version` | `int unsigned` | không | `1` |  |
| `remember_token` | `varchar(100)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(email)`.
- `INDEX(is_active, created_at)`.

CHECK enforce:

`users_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

`users_auth_version_positive_check`:

```sql
auth_version >= 1
```

### DB-02. `roles`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `name` | `varchar(50)` | không | — |  |
| `slug` | `varchar(30)` | không | — |  |
| `description` | `varchar(255)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(slug)`.

CHECK enforce:

`roles_slug_allowed_check`:

```sql
CAST(`slug` AS BINARY) IN ('admin', 'staff', 'customer')
```

### DB-03. `user_roles`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `user_id` | `bigint unsigned` | không | — |  |
| `role_id` | `bigint unsigned` | không | — |  |
| `assigned_by` | `bigint unsigned` | có | — |  |
| `assigned_at` | `datetime(6)` | không | — |  |

FK:

- `user_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `role_id` → `roles.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `assigned_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(user_id, role_id)`.
- `INDEX(role_id, user_id)`.

### DB-04. `password_reset_challenges`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `user_id` | `bigint unsigned` | không | — |  |
| `challenge_token` | `char(64)` | không | — |  |
| `otp_hash` | `varchar(255)` | không | — |  |
| `attempts` | `tinyint unsigned` | không | `0` |  |
| `expires_at` | `datetime(6)` | không | — |  |
| `last_sent_at` | `datetime(6)` | không | — |  |
| `verified_at` | `datetime(6)` | có | — |  |
| `used_at` | `datetime(6)` | có | — |  |
| `invalidated_at` | `datetime(6)` | có | — |  |
| `session_binding_hash` | `char(64)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `user_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(user_id)`.
- `UNIQUE(challenge_token)`.
- `INDEX(expires_at)`.

CHECK enforce:

`password_reset_challenges_attempts_limit_check`:

```sql
attempts <= 5
```

### DB-05. `administrative_units`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `code` | `varchar(20)` | không | — |  |
| `name` | `varchar(150)` | không | — |  |
| `level` | `varchar(20)` | không | — |  |
| `parent_id` | `bigint unsigned` | có | — |  |
| `dataset_version` | `varchar(50)` | không | — |  |
| `valid_from` | `date` | không | — |  |
| `valid_to` | `date` | có | — |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `parent_id` → `administrative_units.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(dataset_version, code)`.
- `INDEX(dataset_version, level, parent_id, is_active)`.

CHECK enforce:

`administrative_units_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

`administrative_units_level_allowed_check`:

```sql
CAST(`level` AS BINARY) IN ('PROVINCE', 'WARD')
```

`administrative_units_parent_level_check`:

```sql
(level = 'PROVINCE' AND parent_id IS NULL) OR (level = 'WARD' AND parent_id IS NOT NULL)
```

`administrative_units_valid_dates_check`:

```sql
valid_to IS NULL OR valid_to >= valid_from
```

### DB-06. `addresses`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `user_id` | `bigint unsigned` | không | — |  |
| `recipient_name` | `varchar(100)` | không | — |  |
| `phone` | `varchar(20)` | không | — |  |
| `province_id` | `bigint unsigned` | không | — |  |
| `ward_id` | `bigint unsigned` | không | — |  |
| `province_code_snapshot` | `varchar(20)` | không | — |  |
| `province_name_snapshot` | `varchar(150)` | không | — |  |
| `ward_code_snapshot` | `varchar(20)` | không | — |  |
| `ward_name_snapshot` | `varchar(150)` | không | — |  |
| `dataset_version` | `varchar(50)` | không | — |  |
| `street_address` | `varchar(255)` | không | — |  |
| `legacy_district_text` | `varchar(150)` | có | — |  |
| `is_default` | `tinyint(1)` | không | `0` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |
| `default_owner_id` | `bigint unsigned` | có | — | STORED GENERATED |

FK:

- `user_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `province_id` → `administrative_units.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `ward_id` → `administrative_units.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `INDEX(user_id, is_default)`.
- `UNIQUE(default_owner_id)`.

Generated expression của `default_owner_id`:

```sql
CASE WHEN is_default = 1 AND deleted_at IS NULL THEN user_id ELSE NULL END
```

CHECK enforce:

`addresses_is_default_boolean_check`:

```sql
`is_default` IN (0, 1)
```

### DB-07. `categories`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `parent_id` | `bigint unsigned` | có | — |  |
| `name` | `varchar(100)` | không | — |  |
| `slug` | `varchar(120)` | không | — |  |
| `gender_group` | `varchar(20)` | không | — |  |
| `description` | `text` | có | — |  |
| `image_path` | `varchar(500)` | có | — |  |
| `display_order` | `smallint unsigned` | không | `1` |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |

FK:

- `parent_id` → `categories.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(slug)`.
- `INDEX(parent_id, display_order)`.

CHECK enforce:

`categories_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

`categories_gender_group_allowed_check`:

```sql
CAST(`gender_group` AS BINARY) IN ('MEN', 'WOMEN', 'KIDS', 'UNISEX')
```

### DB-08. `products`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `category_id` | `bigint unsigned` | không | — |  |
| `name` | `varchar(150)` | không | — |  |
| `slug` | `varchar(180)` | không | — |  |
| `brand_line` | `varchar(100)` | có | — |  |
| `description` | `text` | có | — |  |
| `specifications` | `json` | có | — |  |
| `base_price` | `bigint unsigned` | không | — |  |
| `promotion_price` | `bigint unsigned` | có | — |  |
| `warranty_months` | `smallint unsigned` | không | `6` |  |
| `exchange_days` | `smallint unsigned` | không | `7` |  |
| `rating_avg` | `decimal(3,2)` | không | `0.00` |  |
| `reviews_count` | `int unsigned` | không | `0` |  |
| `is_featured` | `tinyint(1)` | không | `0` |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |

FK:

- `category_id` → `categories.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(slug)`.
- `INDEX(category_id, is_active, created_at)`.

CHECK enforce:

`products_rating_avg_nonnegative_check`:

```sql
`rating_avg` >= 0
```

`products_is_featured_boolean_check`:

```sql
`is_featured` IN (0, 1)
```

`products_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

`products_base_price_positive_check`:

```sql
base_price > 0
```

`products_promotion_price_range_check`:

```sql
promotion_price IS NULL OR (promotion_price > 0 AND promotion_price <= base_price)
```

`products_rating_range_check`:

```sql
rating_avg <= 5
```

### DB-09. `product_colors`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `product_id` | `bigint unsigned` | không | — |  |
| `style_color_code` | `varchar(50)` | không | — |  |
| `color_name` | `varchar(100)` | không | — |  |
| `color_key` | `varchar(20)` | không | — |  |
| `primary_hex` | `char(7)` | không | — |  |
| `display_order` | `smallint unsigned` | không | `1` |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |

FK:

- `product_id` → `products.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(style_color_code)`.
- `INDEX(product_id, is_active, display_order)`.

CHECK enforce:

`product_colors_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

`product_colors_hex_format_check`:

```sql
REGEXP_LIKE(primary_hex, '^#[0-9A-Fa-f]{6}$')
```

### DB-10. `product_images`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `product_color_id` | `bigint unsigned` | không | — |  |
| `source_type` | `varchar(20)` | không | — |  |
| `storage_path` | `varchar(500)` | có | — |  |
| `external_url` | `varchar(1000)` | có | — |  |
| `alt_text` | `varchar(255)` | không | — |  |
| `is_thumbnail` | `tinyint(1)` | không | `0` |  |
| `display_order` | `smallint unsigned` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `thumbnail_color_id` | `bigint unsigned` | có | — | STORED GENERATED |

FK:

- `product_color_id` → `product_colors.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `INDEX(product_color_id, display_order)`.
- `UNIQUE(thumbnail_color_id)`.

Generated expression của `thumbnail_color_id`:

```sql
CASE WHEN is_thumbnail = 1 THEN product_color_id ELSE NULL END
```

CHECK enforce:

`product_images_is_thumbnail_boolean_check`:

```sql
`is_thumbnail` IN (0, 1)
```

`product_images_source_type_allowed_check`:

```sql
CAST(`source_type` AS BINARY) IN ('UPLOAD', 'EXTERNAL_URL')
```

`product_images_exactly_one_source_check`:

```sql
(source_type = 'UPLOAD' AND storage_path IS NOT NULL AND external_url IS NULL) OR (source_type = 'EXTERNAL_URL' AND storage_path IS NULL AND external_url IS NOT NULL)
```

### DB-11. `product_variants`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `product_color_id` | `bigint unsigned` | không | — |  |
| `size_value` | `varchar(10)` | không | — |  |
| `size_numeric` | `decimal(4,1)` | không | — |  |
| `sku` | `varchar(100)` | không | — |  |
| `price_override` | `bigint unsigned` | có | — |  |
| `stock_qty` | `int unsigned` | không | `0` |  |
| `reserved_qty` | `int unsigned` | không | `0` |  |
| `quarantine_qty` | `int unsigned` | không | `0` |  |
| `average_cost` | `decimal(20,4)` | không | `0.0000` |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |

FK:

- `product_color_id` → `product_colors.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(product_color_id, size_value)`.
- `UNIQUE(sku)`.
- `INDEX(product_color_id, is_active, size_numeric)`.

CHECK enforce:

`product_variants_size_numeric_nonnegative_check`:

```sql
`size_numeric` >= 0
```

`product_variants_average_cost_nonnegative_check`:

```sql
`average_cost` >= 0
```

`product_variants_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

`product_variants_size_positive_check`:

```sql
size_numeric > 0
```

`product_variants_price_override_positive_check`:

```sql
price_override IS NULL OR price_override > 0
```

`product_variants_reserved_stock_check`:

```sql
reserved_qty <= stock_qty
```

### DB-12. `suppliers`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `code` | `varchar(50)` | không | — |  |
| `name` | `varchar(150)` | không | — |  |
| `phone` | `varchar(20)` | không | — |  |
| `email` | `varchar(255)` | có | — |  |
| `address` | `varchar(255)` | có | — |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(code)`.

CHECK enforce:

`suppliers_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

### DB-13. `stock_imports`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `import_code` | `varchar(50)` | không | — |  |
| `supplier_id` | `bigint unsigned` | không | — |  |
| `supplier_code_snapshot` | `varchar(50)` | không | — |  |
| `supplier_name_snapshot` | `varchar(150)` | không | — |  |
| `created_by` | `bigint unsigned` | không | — |  |
| `posted_by` | `bigint unsigned` | có | — |  |
| `status` | `varchar(20)` | không | `DRAFT` |  |
| `total_quantity` | `int unsigned` | không | `0` |  |
| `total_cost_amount` | `bigint unsigned` | không | `0` |  |
| `note` | `text` | có | — |  |
| `posted_at` | `datetime(6)` | có | — |  |
| `command_key` | `char(36)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `supplier_id` → `suppliers.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `created_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `posted_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(import_code)`.
- `UNIQUE(command_key)`.
- `INDEX(status, posted_at)`.

CHECK enforce:

`stock_imports_status_allowed_check`:

```sql
CAST(`status` AS BINARY) IN ('DRAFT', 'POSTED', 'CANCELLED')
```

### DB-14. `stock_import_items`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `stock_import_id` | `bigint unsigned` | không | — |  |
| `variant_id` | `bigint unsigned` | không | — |  |
| `sku_snapshot` | `varchar(100)` | không | — |  |
| `quantity` | `int unsigned` | không | — |  |
| `unit_cost_amount` | `bigint unsigned` | không | — |  |
| `subtotal_amount` | `bigint unsigned` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `stock_import_id` → `stock_imports.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(stock_import_id, variant_id)`.

CHECK enforce:

`stock_import_items_quantity_positive_check`:

```sql
quantity > 0
```

`stock_import_items_subtotal_matches_check`:

```sql
subtotal_amount = quantity * unit_cost_amount
```

### DB-15. `inventory_movements`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `variant_id` | `bigint unsigned` | không | — |  |
| `actor_id` | `bigint unsigned` | có | — |  |
| `actor_name_snapshot` | `varchar(100)` | có | — |  |
| `action` | `varchar(40)` | không | — |  |
| `stock_delta` | `int` | không | `0` |  |
| `reserved_delta` | `int` | không | `0` |  |
| `quarantine_delta` | `int` | không | `0` |  |
| `stock_before` | `int unsigned` | không | — |  |
| `stock_after` | `int unsigned` | không | — |  |
| `reserved_before` | `int unsigned` | không | — |  |
| `reserved_after` | `int unsigned` | không | — |  |
| `quarantine_before` | `int unsigned` | không | — |  |
| `quarantine_after` | `int unsigned` | không | — |  |
| `average_cost_before` | `decimal(20,4)` | không | — |  |
| `average_cost_after` | `decimal(20,4)` | không | — |  |
| `unit_cost_basis` | `decimal(20,4)` | có | — |  |
| `reference_type` | `varchar(40)` | không | — |  |
| `reference_id` | `bigint unsigned` | có | — |  |
| `operation_key` | `varchar(180)` | không | — |  |
| `reason` | `varchar(500)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |

FK:

- `variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `actor_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(operation_key)`.
- `INDEX(variant_id, created_at, id)`.
- `INDEX(reference_type, reference_id)`.

CHECK enforce:

`inventory_movements_average_cost_before_nonnegative_check`:

```sql
`average_cost_before` >= 0
```

`inventory_movements_average_cost_after_nonnegative_check`:

```sql
`average_cost_after` >= 0
```

`inventory_movements_unit_cost_basis_nonnegative_check`:

```sql
`unit_cost_basis` >= 0
```

`inventory_movements_reference_type_allowed_check`:

```sql
CAST(`reference_type` AS BINARY) IN ('STOCK_IMPORT', 'ORDER', 'SHIPMENT_RETURN_ITEM', 'AFTER_SALES_REQUEST', 'MANUAL_ADJUSTMENT')
```

`inventory_movements_action_allowed_check`:

```sql
CAST(`action` AS BINARY) IN ('IMPORT_POSTED', 'ORDER_RESERVED', 'ORDER_RELEASED', 'ORDER_DISPATCHED', 'SHIPMENT_RETURN_RECEIVED', 'RETURN_RESTOCKED', 'RETURN_WRITTEN_OFF', 'EXCHANGE_RESERVED', 'EXCHANGE_RELEASED', 'EXCHANGE_OLD_RECEIVED', 'EXCHANGE_COMPLETED_IN', 'EXCHANGE_COMPLETED_OUT', 'DEFECT_RECEIVED', 'MANUAL_ADJUST_IN', 'MANUAL_ADJUST_OUT', 'QUARANTINE_WRITEOFF')
```

`inventory_movements_stock_balance_check`:

```sql
stock_after = CAST(stock_before AS SIGNED) + stock_delta
```

`inventory_movements_reserved_balance_check`:

```sql
reserved_after = CAST(reserved_before AS SIGNED) + reserved_delta
```

`inventory_movements_quarantine_balance_check`:

```sql
quarantine_after = CAST(quarantine_before AS SIGNED) + quarantine_delta
```

`inventory_movements_reserved_bucket_check`:

```sql
reserved_before <= stock_before AND reserved_after <= stock_after
```

### DB-16. `carts`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `user_id` | `bigint unsigned` | có | — |  |
| `guest_token_hash` | `char(64)` | có | — |  |
| `version` | `int unsigned` | không | `1` |  |
| `expires_at` | `datetime(6)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `user_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(user_id)`.
- `UNIQUE(guest_token_hash)`.

CHECK enforce:

`carts_exactly_one_owner_check`:

```sql
(user_id IS NOT NULL AND guest_token_hash IS NULL) OR (user_id IS NULL AND guest_token_hash IS NOT NULL)
```

`carts_version_positive_check`:

```sql
version >= 1
```

### DB-17. `cart_items`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `cart_id` | `bigint unsigned` | không | — |  |
| `variant_id` | `bigint unsigned` | không | — |  |
| `quantity` | `int unsigned` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `cart_id` → `carts.id`, DELETE CASCADE, UPDATE RESTRICT.
- `variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(cart_id, variant_id)`.

CHECK enforce:

`cart_items_quantity_range_check`:

```sql
quantity BETWEEN 1 AND 10
```

### DB-18. `coupons`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `code` | `varchar(50)` | không | — |  |
| `name` | `varchar(150)` | không | — |  |
| `type` | `varchar(30)` | không | — |  |
| `value` | `int unsigned` | không | — |  |
| `min_order_amount` | `bigint unsigned` | không | `0` |  |
| `max_discount_amount` | `bigint unsigned` | có | — |  |
| `usage_limit` | `int unsigned` | có | — |  |
| `per_user_limit` | `int unsigned` | không | `1` |  |
| `starts_at` | `datetime(6)` | có | — |  |
| `expires_at` | `datetime(6)` | có | — |  |
| `is_active` | `tinyint(1)` | không | `1` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `deleted_at` | `datetime(6)` | có | — |  |

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(code)`.
- `INDEX(is_active, starts_at, expires_at)`.

CHECK enforce:

`coupons_is_active_boolean_check`:

```sql
`is_active` IN (0, 1)
```

`coupons_type_allowed_check`:

```sql
CAST(`type` AS BINARY) IN ('PERCENTAGE', 'FIXED', 'FREE_SHIPPING')
```

`coupons_type_value_check`:

```sql
(type = 'PERCENTAGE' AND value BETWEEN 1 AND 100) OR (type = 'FIXED' AND value > 0) OR (type = 'FREE_SHIPPING' AND value = 0 AND max_discount_amount IS NULL)
```

`coupons_per_user_positive_check`:

```sql
per_user_limit >= 1
```

`coupons_valid_period_check`:

```sql
starts_at IS NULL OR expires_at IS NULL OR expires_at > starts_at
```

### DB-19. `coupon_redemptions`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `coupon_id` | `bigint unsigned` | không | — |  |
| `user_id` | `bigint unsigned` | không | — |  |
| `order_id` | `bigint unsigned` | không | — |  |
| `status` | `varchar(20)` | không | `RESERVED` |  |
| `merchandise_discount_amount` | `bigint unsigned` | không | `0` |  |
| `shipping_discount_amount` | `bigint unsigned` | không | `0` |  |
| `reserved_at` | `datetime(6)` | không | — |  |
| `consumed_at` | `datetime(6)` | có | — |  |
| `released_at` | `datetime(6)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `coupon_id` → `coupons.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `user_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `order_id` → `orders.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(order_id)`.
- `INDEX(coupon_id, status, user_id)`.

CHECK enforce:

`coupon_redemptions_status_allowed_check`:

```sql
CAST(`status` AS BINARY) IN ('RESERVED', 'CONSUMED', 'RELEASED')
```

### DB-20. `orders`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_code` | `varchar(50)` | không | — |  |
| `user_id` | `bigint unsigned` | không | — |  |
| `coupon_id` | `bigint unsigned` | có | — |  |
| `coupon_code_snapshot` | `varchar(50)` | có | — |  |
| `subtotal_amount` | `bigint unsigned` | không | — |  |
| `merchandise_discount_amount` | `bigint unsigned` | không | `0` |  |
| `shipping_base_fee` | `bigint unsigned` | không | — |  |
| `shipping_discount_amount` | `bigint unsigned` | không | `0` |  |
| `shipping_fee` | `bigint unsigned` | không | — |  |
| `total_amount` | `bigint unsigned` | không | — |  |
| `order_status` | `varchar(30)` | không | `PENDING` |  |
| `payment_method` | `varchar(20)` | không | — |  |
| `payment_status` | `varchar(30)` | không | `UNPAID` |  |
| `paid_amount` | `bigint unsigned` | không | `0` |  |
| `refunded_amount` | `bigint unsigned` | không | `0` |  |
| `shipping_name` | `varchar(100)` | không | — |  |
| `shipping_phone` | `varchar(20)` | không | — |  |
| `shipping_address_snapshot` | `json` | không | — |  |
| `customer_note` | `varchar(1000)` | có | — |  |
| `cancel_reason` | `varchar(500)` | có | — |  |
| `checkout_key` | `char(36)` | không | — |  |
| `checkout_fingerprint` | `char(64)` | không | — |  |
| `source_cart_version` | `int unsigned` | không | — |  |
| `payment_expires_at` | `datetime(6)` | có | — |  |
| `confirmed_at` | `datetime(6)` | có | — |  |
| `packed_at` | `datetime(6)` | có | — |  |
| `shipped_at` | `datetime(6)` | có | — |  |
| `delivered_at` | `datetime(6)` | có | — |  |
| `cancelled_at` | `datetime(6)` | có | — |  |
| `failed_at` | `datetime(6)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `user_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `coupon_id` → `coupons.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(order_code)`.
- `UNIQUE(user_id, checkout_key)`.
- `INDEX(user_id, created_at)`.
- `INDEX(order_status, payment_expires_at)`.
- `INDEX(delivered_at)`.

CHECK enforce:

`orders_order_status_allowed_check`:

```sql
CAST(`order_status` AS BINARY) IN ('PENDING', 'CONFIRMED', 'PACKING', 'ON_DELIVERY', 'DELIVERED', 'CANCELLED', 'FAILED')
```

`orders_payment_method_allowed_check`:

```sql
CAST(`payment_method` AS BINARY) IN ('COD', 'VIETQR', 'VNPAY')
```

`orders_payment_status_allowed_check`:

```sql
CAST(`payment_status` AS BINARY) IN ('UNPAID', 'PAID', 'REFUND_PENDING', 'PARTIALLY_REFUNDED', 'REFUNDED')
```

`orders_discount_limit_check`:

```sql
merchandise_discount_amount <= subtotal_amount
```

`orders_shipping_discount_limit_check`:

```sql
shipping_discount_amount <= shipping_base_fee
```

`orders_shipping_matches_check`:

```sql
shipping_fee + shipping_discount_amount = shipping_base_fee
```

`orders_total_matches_check`:

```sql
total_amount + merchandise_discount_amount = subtotal_amount + shipping_fee
```

`orders_paid_limit_check`:

```sql
paid_amount <= total_amount
```

`orders_refunded_limit_check`:

```sql
refunded_amount <= paid_amount
```

`orders_cart_version_positive_check`:

```sql
source_cart_version >= 1
```

### DB-21. `order_items`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_id` | `bigint unsigned` | không | — |  |
| `variant_id` | `bigint unsigned` | không | — |  |
| `product_name_snapshot` | `varchar(150)` | không | — |  |
| `style_color_snapshot` | `varchar(50)` | không | — |  |
| `color_name_snapshot` | `varchar(100)` | không | — |  |
| `size_value_snapshot` | `varchar(10)` | không | — |  |
| `sku_snapshot` | `varchar(100)` | không | — |  |
| `thumbnail_snapshot` | `varchar(1000)` | có | — |  |
| `quantity` | `tinyint unsigned` | không | — |  |
| `original_unit_price` | `bigint unsigned` | không | — |  |
| `unit_price` | `bigint unsigned` | không | — |  |
| `subtotal_amount` | `bigint unsigned` | không | — |  |
| `discount_amount` | `bigint unsigned` | không | `0` |  |
| `net_amount` | `bigint unsigned` | không | — |  |
| `exchange_days_snapshot` | `smallint unsigned` | không | — |  |
| `warranty_months_snapshot` | `smallint unsigned` | không | — |  |
| `cost_unit_snapshot` | `decimal(20,4)` | có | — |  |
| `cogs_amount` | `bigint unsigned` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `order_id` → `orders.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(order_id, variant_id)`.

CHECK enforce:

`order_items_cost_unit_snapshot_nonnegative_check`:

```sql
`cost_unit_snapshot` >= 0
```

`order_items_quantity_range_check`:

```sql
quantity BETWEEN 1 AND 10
```

`order_items_prices_positive_check`:

```sql
original_unit_price > 0 AND unit_price > 0
```

`order_items_subtotal_matches_check`:

```sql
subtotal_amount = quantity * unit_price
```

`order_items_net_matches_check`:

```sql
discount_amount <= subtotal_amount AND net_amount + discount_amount = subtotal_amount
```

### DB-22. `order_item_units`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_item_id` | `bigint unsigned` | không | — |  |
| `unit_no` | `tinyint unsigned` | không | — |  |
| `current_variant_id` | `bigint unsigned` | không | — |  |
| `state` | `varchar(30)` | không | `ALLOCATED` |  |
| `gross_amount` | `bigint unsigned` | không | — |  |
| `discount_amount` | `bigint unsigned` | không | `0` |  |
| `net_amount` | `bigint unsigned` | không | — |  |
| `original_cogs_amount` | `bigint unsigned` | có | — |  |
| `current_cogs_amount` | `bigint unsigned` | có | — |  |
| `exchange_completed_count` | `tinyint unsigned` | không | `0` |  |
| `exchange_deadline_at` | `datetime(6)` | có | — |  |
| `warranty_deadline_at` | `datetime(6)` | có | — |  |
| `refunded_merchandise_amount` | `bigint unsigned` | không | `0` |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `order_item_id` → `order_items.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `current_variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(order_item_id, unit_no)`.
- `INDEX(current_variant_id, state)`.

CHECK enforce:

`order_item_units_state_allowed_check`:

```sql
CAST(`state` AS BINARY) IN ('ALLOCATED', 'OUTBOUND', 'OWNED_BY_CUSTOMER', 'RETURNED_TO_STORE', 'LOST', 'RETURNED_REFUNDED', 'CANCELLED')
```

`order_item_units_unit_number_range_check`:

```sql
unit_no BETWEEN 1 AND 10
```

`order_item_units_net_matches_check`:

```sql
discount_amount <= gross_amount AND net_amount + discount_amount = gross_amount
```

`order_item_units_exchange_once_check`:

```sql
exchange_completed_count <= 1
```

`order_item_units_refund_whole_unit_check`:

```sql
refunded_merchandise_amount = 0 OR refunded_merchandise_amount = net_amount
```

### DB-23. `order_status_histories`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_id` | `bigint unsigned` | không | — |  |
| `actor_id` | `bigint unsigned` | có | — |  |
| `actor_name_snapshot` | `varchar(100)` | không | — |  |
| `actor_roles_snapshot` | `json` | có | — |  |
| `from_status` | `varchar(30)` | có | — |  |
| `to_status` | `varchar(30)` | không | — |  |
| `reason_code` | `varchar(50)` | không | — |  |
| `note` | `varchar(1000)` | có | — |  |
| `command_key` | `varchar(180)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |

FK:

- `order_id` → `orders.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `actor_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(command_key)`.
- `INDEX(order_id, created_at, id)`.

CHECK enforce:

`order_status_histories_from_status_allowed_check`:

```sql
CAST(`from_status` AS BINARY) IN ('PENDING', 'CONFIRMED', 'PACKING', 'ON_DELIVERY', 'DELIVERED', 'CANCELLED', 'FAILED')
```

`order_status_histories_to_status_allowed_check`:

```sql
CAST(`to_status` AS BINARY) IN ('PENDING', 'CONFIRMED', 'PACKING', 'ON_DELIVERY', 'DELIVERED', 'CANCELLED', 'FAILED')
```

### DB-24. `payments`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_id` | `bigint unsigned` | không | — |  |
| `origin_payment_id` | `bigint unsigned` | có | — |  |
| `attempt_ref` | `varchar(100)` | không | — |  |
| `method` | `varchar(20)` | không | — |  |
| `status` | `varchar(20)` | không | `PENDING` |  |
| `disposition` | `varchar(30)` | không | `NONE` |  |
| `expected_amount` | `bigint unsigned` | không | — |  |
| `received_amount` | `bigint unsigned` | không | `0` |  |
| `applied_amount` | `bigint unsigned` | không | `0` |  |
| `currency` | `char(3)` | không | `VND` |  |
| `provider_transaction_id` | `varchar(150)` | có | — |  |
| `bank_transfer_content` | `varchar(255)` | có | — |  |
| `customer_reported_reference` | `varchar(150)` | có | — |  |
| `customer_reported_evidence_path` | `varchar(500)` | có | — |  |
| `customer_reported_at` | `datetime(6)` | có | — |  |
| `confirmed_by` | `bigint unsigned` | có | — |  |
| `confirmation_evidence_path` | `varchar(500)` | có | — |  |
| `expires_at` | `datetime(6)` | có | — |  |
| `succeeded_at` | `datetime(6)` | có | — |  |
| `idempotency_key` | `varchar(180)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `pending_order_id` | `bigint unsigned` | có | — | STORED GENERATED |

FK:

- `order_id` → `orders.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `origin_payment_id` → `payments.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `confirmed_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(attempt_ref)`.
- `UNIQUE(idempotency_key)`.
- `UNIQUE(method, provider_transaction_id)`.
- `INDEX(order_id, status)`.
- `UNIQUE(pending_order_id)`.

Generated expression của `pending_order_id`:

```sql
CASE WHEN status = 'PENDING' THEN order_id ELSE NULL END
```

CHECK enforce:

`payments_method_allowed_check`:

```sql
CAST(`method` AS BINARY) IN ('COD', 'VIETQR', 'VNPAY')
```

`payments_status_allowed_check`:

```sql
CAST(`status` AS BINARY) IN ('PENDING', 'SUCCEEDED', 'FAILED', 'EXPIRED')
```

`payments_disposition_allowed_check`:

```sql
CAST(`disposition` AS BINARY) IN ('NONE', 'APPLIED', 'UNAPPLIED_LATE', 'UNAPPLIED_DUPLICATE', 'UNAPPLIED_MISMATCH')
```

`payments_currency_vnd_check`:

```sql
CAST(currency AS BINARY) = 'VND'
```

`payments_applied_limit_check`:

```sql
applied_amount <= received_amount
```

### DB-25. `payment_events`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `payment_id` | `bigint unsigned` | có | — |  |
| `provider` | `varchar(30)` | không | — |  |
| `event_key` | `char(64)` | không | — |  |
| `provider_event_ref` | `varchar(150)` | có | — |  |
| `signature_valid` | `tinyint(1)` | không | — |  |
| `processing_status` | `varchar(30)` | không | — |  |
| `payload_redacted` | `json` | không | — |  |
| `response_code` | `varchar(20)` | có | — |  |
| `received_at` | `datetime(6)` | không | — |  |
| `processed_at` | `datetime(6)` | có | — |  |

FK:

- `payment_id` → `payments.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(event_key)`.
- `INDEX(payment_id, received_at)`.

CHECK enforce:

`payment_events_signature_valid_boolean_check`:

```sql
`signature_valid` IN (0, 1)
```

`payment_events_provider_allowed_check`:

```sql
CAST(`provider` AS BINARY) IN ('VNPAY', 'VIETQR', 'COD')
```

`payment_events_processing_status_allowed_check`:

```sql
CAST(`processing_status` AS BINARY) IN ('RECEIVED', 'APPLIED', 'DUPLICATE', 'REVIEW', 'REJECTED')
```

### DB-26. `refunds`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_id` | `bigint unsigned` | không | — |  |
| `payment_id` | `bigint unsigned` | không | — |  |
| `after_sales_request_id` | `bigint unsigned` | có | — |  |
| `order_item_unit_id` | `bigint unsigned` | có | — |  |
| `reason_type` | `varchar(30)` | không | — |  |
| `status` | `varchar(20)` | không | `REQUESTED` |  |
| `merchandise_amount` | `bigint unsigned` | không | `0` |  |
| `shipping_amount` | `bigint unsigned` | không | `0` |  |
| `amount` | `bigint unsigned` | không | — |  |
| `operation_key` | `varchar(180)` | không | — |  |
| `destination_type` | `varchar(20)` | không | — |  |
| `destination_ciphertext` | `text` | có | — |  |
| `requested_by` | `bigint unsigned` | có | — |  |
| `approved_by` | `bigint unsigned` | có | — |  |
| `paid_by` | `bigint unsigned` | có | — |  |
| `external_reference` | `varchar(150)` | có | — |  |
| `evidence_path` | `varchar(500)` | có | — |  |
| `failure_reason` | `varchar(500)` | có | — |  |
| `succeeded_at` | `datetime(6)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `order_id` → `orders.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `payment_id` → `payments.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `after_sales_request_id` → `after_sales_requests.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `order_item_unit_id` → `order_item_units.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `requested_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `approved_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `paid_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(operation_key)`.
- `UNIQUE(external_reference)`.
- `INDEX(payment_id, status)`.
- `INDEX(order_item_unit_id, status)`.

CHECK enforce:

`refunds_status_allowed_check`:

```sql
CAST(`status` AS BINARY) IN ('REQUESTED', 'APPROVED', 'PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELLED')
```

`refunds_reason_type_allowed_check`:

```sql
CAST(`reason_type` AS BINARY) IN ('ORDER_CANCELLED', 'SHIPMENT_FAILED', 'DEFECT_RETURN', 'LATE_PAYMENT', 'DUPLICATE_PAYMENT', 'MISMATCH_PAYMENT', 'SHIPPING_RETURN')
```

`refunds_destination_type_allowed_check`:

```sql
CAST(`destination_type` AS BINARY) IN ('BANK_TRANSFER', 'CASH')
```

`refunds_positive_sum_check`:

```sql
amount > 0 AND amount = merchandise_amount + shipping_amount
```

### DB-27. `shipments`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_id` | `bigint unsigned` | không | — |  |
| `tracking_code` | `varchar(100)` | có | — |  |
| `carrier_name` | `varchar(100)` | không | — |  |
| `status` | `varchar(30)` | không | `READY` |  |
| `created_by` | `bigint unsigned` | không | — |  |
| `shipped_at` | `datetime(6)` | có | — |  |
| `delivered_at` | `datetime(6)` | có | — |  |
| `failed_at` | `datetime(6)` | có | — |  |
| `return_received_at` | `datetime(6)` | có | — |  |
| `proof_path` | `varchar(500)` | có | — |  |
| `note` | `varchar(1000)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `order_id` → `orders.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `created_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(order_id)`.
- `INDEX(status, shipped_at)`.

CHECK enforce:

`shipments_status_allowed_check`:

```sql
CAST(`status` AS BINARY) IN ('READY', 'IN_TRANSIT', 'DELIVERED', 'RETURNING', 'RETURN_RECEIVED', 'LOST')
```

### DB-28. `shipment_return_items`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `shipment_id` | `bigint unsigned` | không | — |  |
| `order_item_unit_id` | `bigint unsigned` | không | — |  |
| `variant_id` | `bigint unsigned` | không | — |  |
| `received_by` | `bigint unsigned` | không | — |  |
| `received_at` | `datetime(6)` | không | — |  |
| `disposition` | `varchar(20)` | không | `PENDING` |  |
| `cost_basis` | `decimal(20,4)` | không | — |  |
| `inspected_by` | `bigint unsigned` | có | — |  |
| `inspected_at` | `datetime(6)` | có | — |  |
| `note` | `varchar(1000)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `shipment_id` → `shipments.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `order_item_unit_id` → `order_item_units.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `received_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `inspected_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(order_item_unit_id)`.
- `INDEX(shipment_id, disposition)`.

CHECK enforce:

`shipment_return_items_cost_basis_nonnegative_check`:

```sql
`cost_basis` >= 0
```

`shipment_return_items_disposition_allowed_check`:

```sql
CAST(`disposition` AS BINARY) IN ('PENDING', 'RESTOCK', 'WRITE_OFF')
```

### DB-29. `reviews`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_item_unit_id` | `bigint unsigned` | không | — |  |
| `user_id` | `bigint unsigned` | không | — |  |
| `rating` | `decimal(2,1)` | không | — |  |
| `comment` | `text` | không | — |  |
| `admin_reply` | `text` | có | — |  |
| `is_visible` | `tinyint(1)` | không | `1` |  |
| `replied_by` | `bigint unsigned` | có | — |  |
| `replied_at` | `datetime(6)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

FK:

- `order_item_unit_id` → `order_item_units.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `user_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `replied_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(order_item_unit_id)`.
- `INDEX(user_id, created_at)`.

CHECK enforce:

`reviews_is_visible_boolean_check`:

```sql
`is_visible` IN (0, 1)
```

`reviews_rating_half_steps_check`:

```sql
rating >= 0.5 AND rating <= 5 AND MOD(rating * 10, 5) = 0
```

`reviews_comment_length_check`:

```sql
CHAR_LENGTH(comment) BETWEEN 10 AND 2000
```

`reviews_reply_length_check`:

```sql
admin_reply IS NULL OR CHAR_LENGTH(admin_reply) <= 2000
```

### DB-30. `after_sales_requests`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `request_code` | `varchar(50)` | không | — |  |
| `order_item_unit_id` | `bigint unsigned` | không | — |  |
| `type` | `varchar(30)` | không | — |  |
| `status` | `varchar(30)` | không | `NEW` |  |
| `source_variant_id` | `bigint unsigned` | không | — |  |
| `target_variant_id` | `bigint unsigned` | có | — |  |
| `target_reservation_active` | `tinyint(1)` | không | `0` |  |
| `source_in_quarantine` | `tinyint(1)` | không | `0` |  |
| `return_disposition` | `varchar(20)` | không | `PENDING` |  |
| `disposition_by` | `bigint unsigned` | có | — |  |
| `disposition_at` | `datetime(6)` | có | — |  |
| `customer_reason` | `text` | không | — |  |
| `staff_note` | `text` | có | — |  |
| `handled_by` | `bigint unsigned` | có | — |  |
| `receive_by_at` | `datetime(6)` | có | — |  |
| `received_at` | `datetime(6)` | có | — |  |
| `accepted_at` | `datetime(6)` | có | — |  |
| `ready_at` | `datetime(6)` | có | — |  |
| `customer_handover_at` | `datetime(6)` | có | — |  |
| `returned_cogs_amount` | `bigint unsigned` | có | — |  |
| `replacement_cogs_amount` | `bigint unsigned` | có | — |  |
| `repair_expense_amount` | `bigint unsigned` | không | `0` |  |
| `repair_expense_approved_by` | `bigint unsigned` | có | — |  |
| `repair_expense_approved_at` | `datetime(6)` | có | — |  |
| `resolved_at` | `datetime(6)` | có | — |  |
| `command_key` | `varchar(180)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |
| `active_unit_id` | `bigint unsigned` | có | — | STORED GENERATED |

FK:

- `order_item_unit_id` → `order_item_units.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `source_variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `target_variant_id` → `product_variants.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `disposition_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `handled_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `repair_expense_approved_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(request_code)`.
- `UNIQUE(command_key)`.
- `UNIQUE(active_unit_id)`.
- `INDEX(status, receive_by_at)`.

Generated expression của `active_unit_id`:

```sql
CASE WHEN status IN ('NEW', 'APPROVED', 'RECEIVED', 'PROCESSING', 'AWAITING_REFUND', 'READY_FOR_PICKUP') THEN order_item_unit_id ELSE NULL END
```

CHECK enforce:

`after_sales_requests_target_reservation_active_boolean_check`:

```sql
`target_reservation_active` IN (0, 1)
```

`after_sales_requests_source_in_quarantine_boolean_check`:

```sql
`source_in_quarantine` IN (0, 1)
```

`after_sales_requests_type_allowed_check`:

```sql
CAST(`type` AS BINARY) IN ('SIZE_EXCHANGE', 'WARRANTY_REPAIR', 'DEFECT_RETURN')
```

`after_sales_requests_status_allowed_check`:

```sql
CAST(`status` AS BINARY) IN ('NEW', 'APPROVED', 'RECEIVED', 'PROCESSING', 'AWAITING_REFUND', 'READY_FOR_PICKUP', 'COMPLETED', 'REJECTED', 'CANCELLED')
```

`after_sales_requests_return_disposition_allowed_check`:

```sql
CAST(`return_disposition` AS BINARY) IN ('PENDING', 'RESTOCK', 'WRITE_OFF')
```

`after_sales_requests_target_type_check`:

```sql
(type = 'SIZE_EXCHANGE' AND target_variant_id IS NOT NULL AND target_variant_id <> source_variant_id) OR (type <> 'SIZE_EXCHANGE' AND target_variant_id IS NULL)
```

`after_sales_requests_warranty_custody_check`:

```sql
type <> 'WARRANTY_REPAIR' OR source_in_quarantine = 0
```

`after_sales_requests_reason_length_check`:

```sql
CHAR_LENGTH(customer_reason) BETWEEN 10 AND 2000
```

### DB-31. `after_sales_histories`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `after_sales_request_id` | `bigint unsigned` | không | — |  |
| `actor_id` | `bigint unsigned` | có | — |  |
| `actor_name_snapshot` | `varchar(100)` | không | — |  |
| `from_status` | `varchar(30)` | có | — |  |
| `to_status` | `varchar(30)` | không | — |  |
| `event_type` | `varchar(40)` | không | — |  |
| `visibility` | `varchar(20)` | không | `CUSTOMER` |  |
| `note` | `varchar(2000)` | có | — |  |
| `command_key` | `varchar(180)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |

FK:

- `after_sales_request_id` → `after_sales_requests.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `actor_id` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(command_key)`.
- `INDEX(after_sales_request_id, created_at, id)`.

CHECK enforce:

`after_sales_histories_from_status_allowed_check`:

```sql
CAST(`from_status` AS BINARY) IN ('NEW', 'APPROVED', 'RECEIVED', 'PROCESSING', 'AWAITING_REFUND', 'READY_FOR_PICKUP', 'COMPLETED', 'REJECTED', 'CANCELLED')
```

`after_sales_histories_to_status_allowed_check`:

```sql
CAST(`to_status` AS BINARY) IN ('NEW', 'APPROVED', 'RECEIVED', 'PROCESSING', 'AWAITING_REFUND', 'READY_FOR_PICKUP', 'COMPLETED', 'REJECTED', 'CANCELLED')
```

`after_sales_histories_event_type_allowed_check`:

```sql
CAST(`event_type` AS BINARY) IN ('CREATED', 'TRANSITION', 'INSPECTED', 'HANDOVER', 'NOTE', 'DEADLINE_EXTENDED')
```

`after_sales_histories_visibility_allowed_check`:

```sql
CAST(`visibility` AS BINARY) IN ('CUSTOMER', 'INTERNAL')
```

### DB-32. `after_sales_attachments`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `after_sales_request_id` | `bigint unsigned` | không | — |  |
| `uploaded_by` | `bigint unsigned` | không | — |  |
| `disk` | `varchar(20)` | không | `private` |  |
| `path` | `varchar(500)` | không | — |  |
| `mime_type` | `varchar(100)` | không | — |  |
| `size_bytes` | `int unsigned` | không | — |  |
| `sha256` | `char(64)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |

FK:

- `after_sales_request_id` → `after_sales_requests.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `uploaded_by` → `users.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(path)`.
- `INDEX(after_sales_request_id, created_at)`.

CHECK enforce:

`after_sales_attachments_disk_allowed_check`:

```sql
CAST(`disk` AS BINARY) IN ('private')
```

`after_sales_attachments_mime_type_allowed_check`:

```sql
CAST(`mime_type` AS BINARY) IN ('image/jpeg', 'image/png', 'image/webp')
```

`after_sales_attachments_file_size_check`:

```sql
size_bytes BETWEEN 1 AND 5242880
```

### DB-33. `sales_ledger_entries`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `order_id` | `bigint unsigned` | có | — |  |
| `order_item_unit_id` | `bigint unsigned` | có | — |  |
| `after_sales_request_id` | `bigint unsigned` | có | — |  |
| `refund_id` | `bigint unsigned` | có | — |  |
| `inventory_movement_id` | `bigint unsigned` | có | — |  |
| `type` | `varchar(40)` | không | — |  |
| `merchandise_revenue_delta` | `bigint` | không | `0` |  |
| `shipping_revenue_delta` | `bigint` | không | `0` |  |
| `cogs_delta` | `bigint` | không | `0` |  |
| `expense_delta` | `bigint` | không | `0` |  |
| `operation_key` | `varchar(180)` | không | — |  |
| `occurred_at` | `datetime(6)` | không | — |  |
| `created_at` | `datetime(6)` | không | — |  |

FK:

- `order_id` → `orders.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `order_item_unit_id` → `order_item_units.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `after_sales_request_id` → `after_sales_requests.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `refund_id` → `refunds.id`, DELETE RESTRICT, UPDATE RESTRICT.
- `inventory_movement_id` → `inventory_movements.id`, DELETE RESTRICT, UPDATE RESTRICT.

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(operation_key)`.
- `INDEX(occurred_at, type)`.
- `INDEX(order_id, order_item_unit_id)`.

CHECK enforce:

`sales_ledger_entries_type_allowed_check`:

```sql
CAST(`type` AS BINARY) IN ('SALE_RECOGNIZED', 'SHIPPING_RECOGNIZED', 'SALE_REVERSED', 'SHIPPING_REVERSED', 'EXCHANGE_COGS_ADJUSTMENT', 'RMA_EXPENSE', 'INVENTORY_LOSS')
```

`sales_ledger_entries_source_required_check`:

```sql
order_id IS NOT NULL OR inventory_movement_id IS NOT NULL
```

### DB-34. `outbox_events`

| Cột | Kiểu MySQL | Nullable | Default | Đặc tính |
|---|---|---|---|---|
| `id` | `bigint unsigned` | không | — | PK, AUTO_INCREMENT |
| `event_type` | `varchar(80)` | không | — |  |
| `aggregate_type` | `varchar(40)` | không | — |  |
| `aggregate_id` | `bigint unsigned` | không | — |  |
| `dedup_key` | `varchar(180)` | không | — |  |
| `payload` | `json` | không | — |  |
| `status` | `varchar(20)` | không | `PENDING` |  |
| `attempts` | `smallint unsigned` | không | `0` |  |
| `available_at` | `datetime(6)` | không | — |  |
| `lease_until` | `datetime(6)` | có | — |  |
| `sent_at` | `datetime(6)` | có | — |  |
| `last_error` | `varchar(1000)` | có | — |  |
| `created_at` | `datetime(6)` | không | — |  |
| `updated_at` | `datetime(6)` | không | — |  |

Index khai báo (ngoài PK/index FK tự sinh):

- `UNIQUE(dedup_key)`.
- `INDEX(status, available_at)`.
- `INDEX(lease_until)`.

CHECK enforce:

`outbox_events_status_allowed_check`:

```sql
CAST(`status` AS BINARY) IN ('PENDING', 'PROCESSING', 'SENT', 'FAILED')
```

`outbox_events_aggregate_type_allowed_check`:

```sql
CAST(`aggregate_type` AS BINARY) IN ('ORDER', 'RMA', 'REFUND', 'USER')
```

## Sáu bảng hạ tầng hiện hữu

`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` dùng schema framework từ P00, không đếm lẫn vào34 bảng miền. `sessions.user_id` nullable/index theo framework, chưa có FK SQL tới users; đây là chủ ý giữ compatibility hạ tầng. Bảng `migrations` do Laravel quản lý, không phải bảng miền.

## Công việc tiếp theo

P01.02 đã bổ sung Eloquent models/relationships/casts và Resource whitelist. P01.03 tiếp tục Money/clock/exception contract và tiện ích trên các enum đã có; P01.04 bổ sung seed nền, state/factory nghiệp vụ và dataset địa giới có manifest. Chưa seed tài khoản mặc định hoặc tạo workflow bán hàng; giao diện vẫn giữ nguyên.

## Model và serialization sau P01.02

-34 model miền tương ứng34 bảng, dùng DomainModel chung cho HasFactory/dateFormat, ngoại lệ User extends Authenticatable và UserRole extends Pivot. Có147 quan hệ trả kiểu cụ thể, bao gồm70 belongsTo theo FK, inverse relations, custom role pivot, thumbnail và Product.variants qua ProductColor.
- User.roles / Role.users dùng UserRole qua user_roles, pivot alias `assignment` và các trường id/assigned_by/assigned_at; pivot có auto-increment ID, không created_at/updated_at. User.hasRole tra slug mới từ DB, không tin collection roles cũ đã load.
- Các bảng inventory_movements, order_status_histories, after_sales_histories, after_sales_attachments, sales_ledger_entries chỉ tự ghi created_at, UPDATED_AT=null. UserRole và PaymentEvent không dùng timestamps mặc định vì schema không có hai cột này.
- Mọi date/datetime khai báo immutable_date/immutable_datetime, dateFormat `Y-m-d H:i:s.u`; tiền/delta và IDs cast integer, cost/rating/size_numeric giữ decimal string, JSON cast array, password hashed. Refund.destination_ciphertext dùng encrypted:array và bị ẩn khi serialize model/Resource.
-31 enum có18 enum canonical trong §5.2 và các enum cho discriminator/domain hữu hạn còn lại: level/gender/source/reference/event/visibility/disk/MIME/currency/aggregate. Case PHP TitleCase, backing value giữ đúng VARCHAR trong DB. P01.03 bổ sung label() cho các enum hiện hữu, ErrorCode riêng (29 mã lỗi), Money/clock và exception contract; không đổi backing values hoặc schema.
- Model chỉ fillable các trường mô tả/đầu vào không làm thay đổi privilege, owner hoặc số dư; chứng từ, role, stock/cost, trạng thái tiền, helper và FK owner không nhận mass assignment. Password cũng không nằm trong fillable hồ sơ: AuthService sau này phải gán có chủ đích cùng workflow auth_version.
- UserRole bị guarded toàn bộ, bao gồm metadata assigned_at/assigned_by. Vì Laravel attach/sync metadata dùng fill(), RoleService sau này cần tạo/cập nhật pivot bằng thuộc tính server đã kiểm quyền và gán tường minh, thay vì mở lại guarded hoặc truyền request vào forceFill. Factory tạo assignment trong DB test bằng cơ chế unguarded nội bộ của Factory; đó không phải endpoint cấp quyền.
- Các quan hệ catalog bình thường vẫn áp SoftDeletes. OrderItem.variant, Unit.currentVariant và sourceVariant/targetVariant của RMA dùng withTrashed cho lịch sử. Muốn đi tiếp qua color/product/category đã xóa mềm dùng historicalColor/historicalProduct/historicalCategory rõ ràng; không tắt soft-delete scope toàn cục. Chứng từ Resource đọc snapshot đã lưu, không thay bằng catalog hiện tại.
-34 Resource Admin kiểm current active admin role và trả403 nếu guest/customer/Staff/khóa/xóa mềm/thu hồi role;19 Resource Customer dùng whitelist riêng, Resource tài liệu cá nhân kiểm owner và trả404 cho khách/owner khác. Staff vận hành về sau cần Resource không chứa cost riêng theo quyền module, không được dùng Admin tài chính như một DTO chung.
- Customer không có cost/cogs, private proof path, staff_note, credential/challenge/hash/session keys hoặc generated helper; RMA history chỉ trả visibility CUSTOMER. Review bị ẩn bị bỏ khỏi tài liệu đơn, không làm cả đơn404. Admin được xem trường cost theo whitelist nhưng không nhận raw credential, bank destination ciphertext hoặc private proof path.
- Resource chỉ đưa relation đã load vào payload và không gọi model.toArray() để lấy toàn bộ field. Catalog eager-load category/colors.thumbnail/colors.variants có số query cố định; serialize catalog đã load không phát sinh query. Nested document ownership dùng loadMissing cho chain cần kiểm, không sinh FK order_id/user_id trùng trên RMA.
- Đây là lớp model/DTO nền. P01.03 đã nối JSON success envelope/error contract và request ID vào bootstrap/Resource base, chưa có endpoint nghiệp vụ mới, middleware phân quyền/Policy, guest-cart token resolver hoặc workflow bán hàng. Customer CartResource hiện fail-closed cho guest cart chưa có owner user; CartService/guest resolver sẽ được tích hợp đúng P05.

Factory34 bảng chỉ tạo cấu trúc hợp lệ để test relationships/casts/serialization, có các state inactive/administrator/thumbnail/stocked/succeeded/guest cần cho test. Không seed dữ liệu production/development, không giả rằng factory succeeded/stocked đã chạy PaymentService/InventoryService hay tạo đủ ledger/outbox. Dữ liệu mẫu, phân quyền seed, manifest địa giới và kịch bản nghiệp vụ hoàn chỉnh thuộc các task sau.

Tham khảo kỹ thuật: [Laravel13 migrations](https://laravel.com/docs/13.x/migrations), [MySQL8.4 CHECK](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html), [generated indexes](https://dev.mysql.com/doc/refman/8.4/en/create-table-secondary-indexes.html).
