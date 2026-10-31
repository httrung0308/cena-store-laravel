# EXT-003 — Đối chiếu UI Cena gốc và kiểm kê asset (PREP)

- **Phase tham chiếu:** P03.01 / P05.01; **trạng thái:** tài liệu chuẩn bị, không hoàn tất phase hay bật route.
- **Upstream `main` được đọc tại:** `d2771892dddc6aa20f8fce29dc11df59b3f566a7` (BASE_SHA, 31/10/2026).
- **Nguồn quyết định:** `docs/architecture/decisions.md` (ADR-002), `docs/specification.md` (P03.01, P05.01, WF-06/WF-23), `docs/reference/legacy-ui/README.md` và `docs/reference/legacy-ui/manifest.json`.
- Manifest ghi **75 tệp** TSX/CSS/helpers và SHA-256 của từng tệp; tại BASE_SHA, **75/75 đường dẫn tương ứng tồn tại** trong `docs/reference/legacy-ui/`. Đây là snapshot **chỉ đọc**, không có đầy đủ Next pages/import/dependencies, không phải 75 tệp runtime có thể chạy. Không copy logic JWT/Recoil/Next/API/Stripe từ snapshot thành hợp đồng Laravel.

## Màn hình và module: reference thật → Blade/Laravel

Các đường dẫn ở cột **reference** đều đã có trong snapshot. Cột **Laravel** phân biệt tệp hiện hữu và màn **dự kiến, chưa triển khai**; không suy diễn route từ JSX cũ.

| Màn / phạm vi | Reference gốc (dưới `docs/reference/legacy-ui/`) | Laravel hiện tại / đích canonical |
| --- | --- | --- |
| Shell/navbar/footer/menu | `common/layouts/main.tsx`, `common/components/nav/components/NavBar.tsx`, `common/components/nav/components/NavMenu.tsx`, `common/components/footer/components/Footer.tsx` | **Đã có shell P00:** `resources/views/components/storefront/navigation.blade.php`, `resources/views/components/storefront/footer.blade.php`; P03.01 bổ sung layout dùng chung, quyền menu và accessibility theo contract. |
| Home + Men/Women/Kid/Unisex | `modules/home/components/Home.tsx`, `modules/home/components/CollectionList.tsx`, `modules/home/components/Collection.tsx` | **Đã có bản port P00:** `resources/views/storefront/home.blade.php`, `resources/views/components/storefront/collection.blade.php`; route `home` (`/`) trong `routes/web.php`. P05.01 mới nối data/banner/latest/sale thật. |
| Login / đăng ký | `modules/login/components/Login.tsx`, `modules/login/components/LoginForm.tsx`, `modules/register/components/Register.tsx`, `modules/register/components/RegistrationForm.tsx`, `modules/register/components/Background.tsx` | **Chưa có Blade/route auth.** UI theo P02.01 và auth layout P03.01; video background **chỉ** xuất hiện ở màn đăng ký trong snapshot, không phải login. |
| Quên/đặt lại mật khẩu | `modules/forgot-password/components/ForgotPassword.tsx`, `modules/reset-password/components/ResetPassword.tsx` | **Chưa có màn chạy**; P02.03 cần OTP/CSRF/service thật, không tái sử dụng cách reset legacy. |
| Catalog (list/card/filter) | `modules/products/components/ProductList.tsx`, `modules/products/components/Product.tsx`, `modules/products/components/filter/Filter.tsx`, `modules/products/helpers/filterProducts.ts` | **Chưa có màn list chạy.** Dự kiến P05.01, route canonical `catalog.index` (`/shoes`) sau gate; filter/pagination từ server theo WF-06, không chỉ lọc trang hiện tại. |
| Detail, ảnh/màu/size/size-guide/review | `modules/productDetails/components/ProductDetails.tsx`, `modules/productDetails/components/ProductGallery.tsx`, `modules/productDetails/components/ProductVariant.tsx`, `modules/productDetails/components/Size.tsx`, `modules/productDetails/modals/SizeGuide.tsx`, `modules/productDetails/modals/Reviews.tsx` | **Chưa có màn detail chạy.** P05.01 dùng `/products/{slug}` và query `style_color` theo WF-06; giá/tồn/size là dữ liệu service, không dùng URL/size giả từ TSX. |
| Cart drawer / line | `common/components/cart/components/Cart.tsx`, `common/components/cart/components/CartProduct.tsx` | **Chỉ có shell rỗng P00** tại `resources/views/components/storefront/panels.blade.php` (Cart (0), notice, không ghi DB); cart thật thuộc P05.02–P05.04. |
| Checkout | `modules/checkout/components/Checkout.tsx`, `modules/checkout/components/CheckoutForm.tsx`, `modules/checkout/components/AddressForm.tsx`, `modules/checkout/components/PriceDetails.tsx` | **Chưa có màn/route checkout.** P06.03 phụ thuộc service/quote/stock thật; không port thanh toán Strapi/Stripe. |
| Tài khoản / lịch sử đơn | `modules/account/components/Account.tsx`, `modules/account/components/AddressManagement.tsx`, `modules/account/components/OrderHistory.tsx` | **Chưa có Blade account/order.** Account P02.04–P02.05; danh sách/chi tiết đơn Customer P08.01/08.04, chỉ đọc snapshot đơn của đúng owner. |
| Admin đơn / catalog / người dùng | `modules/admin/components/orders/AdminOrdersPage.tsx`, `modules/admin/components/orders/AdminOrderDetail.tsx`, `modules/admin/components/products/AdminProductsPage.tsx`, `modules/admin/components/users/AdminUsersPage.tsx` | **Chưa có Admin UI chạy.** Catalog Admin P03.02–P03.03; đơn P08; user/role P02, đều cần guard thực theo ADR/WF. |
| Dialog/toast/buttons shared | `common/components/modal/components/ModalManager.tsx`, `common/components/toast/Toast.tsx`, `common/components/button/components/LoaderButton.tsx`, `common/components/input/components/InputComponent.tsx` | **Chưa port thành shared module đầy đủ**; P03.01 chuẩn bị Blade/Tailwind 3/Alpine 3. P00 đã có panel/menu đơn giản tại `resources/views/components/storefront/panels.blade.php`. |

**Trạng thái route tại BASE_SHA:** `routes/web.php` chỉ khai báo `home` (`/`); các route catalog/auth/detail/cart/order được nhắc tới là **dự kiến**, không phải endpoint có sẵn. P00 hiển thị thông báo “sắp mở” và drawer trống, không chứng minh nghiệp vụ hoàn chỉnh. Giữ Montserrat, nền trắng/chữ đen, chữ **Cena**, “Sport, the smart choice.”, các collection và attribution gốc; không hứa pixel-perfect khi chưa đối chiếu browser với bản gốc chạy được.

## Asset local có thật / nguồn và quyền sử dụng

6 asset dưới `public/legacy/` đã được commit. **SHA-256 dưới đây lấy từ** `docs/evidence/legacy-assets.json` (bản đối chiếu nguồn P00; chưa tái băm binary ở môi trường lập tài liệu), **không phải Git blob SHA**. `frontend/public/...` trong cột nguồn là đường dẫn **source cũ bên ngoài repo Laravel**, không phải đường dẫn reference mới.

| Asset hiện có | Nguồn bên frontend cũ | SHA-256 theo evidence |
| --- | --- | --- |
| `public/legacy/img/main.jpg` | `frontend/public/img/main.jpg` | `217841b96c302de53b039b1d48e0313e8dfa42cf028ad9ab975149ac27ae306b` |
| `public/legacy/img/men.jpg` | `frontend/public/img/men.jpg` | `0114a76f769140f791b1e6d991ab0af60ef9eb661301e98e2ac33e6c70c89454` |
| `public/legacy/img/women.jpg` | `frontend/public/img/women.jpg` | `1c0e26e44a0817b762b076e84c27e7987c0210bf4914f4ffb1352f1202204e42` |
| `public/legacy/img/kid.jpg` | `frontend/public/img/kid.jpg` | `19606b4a9511dc9fa9c3f4c042ca25986a5fd68b971f2a8635b37497854c9108` |
| `public/legacy/img/unisex.jpg` | `frontend/public/img/unisex.jpg` | `26d3a77086a7410f0c43f1afd15172ef7597bbaa3b6401f29409ea813e06d2b5` |
| `public/legacy/favicon.ico` | `frontend/public/favicon.ico` | `b9e0c239b57dfb2242f8a888c2702e0b121279ec88d6aef0acfb196e48adf311` |

- **Nguồn/license:** snapshot Cena có `LICENSE-legacy-ui` (MIT, copyright 2025 cena261); icon SVG nhúng trong `resources/views/components/storefront/icon.blade.php` dùng path React Icons với `LICENSE-react-icons`. Montserrat tải qua dependency `@fontsource/montserrat` theo `resources/css/app.css`, giấy phép OFL trong `LICENSE-Montserrat`; không có tệp font riêng ở `public/legacy` để báo hash. Không thay chữ/logo Cena.
- **Lưu ý bản quyền ảnh:** code MIT **không tự xác nhận quyền tái phân phối ảnh bên thứ ba**. Footer gốc tại `resources/views/components/storefront/footer.blade.php` ghi nguồn ảnh sản phẩm `nike.com`. Owner cần xác minh quyền/attribution của từng ảnh trước khi mở rộng sử dụng; không gán nhãn license MIT cho ảnh Nike khi chưa có bằng chứng.
- `public/favicon.ico` trong repo là file **0 byte**; favicon sử dụng ở home là `public/legacy/favicon.ico`. Không thay thế cả hai trong EXT-003.

## Chưa có / cần owner quyết định (không download, không tạo placeholder)

| Thiếu / nguồn phát hiện | Trạng thái kiểm kê và quyết định cần có |
| --- | --- |
| `./video/register.mp4` trong `modules/register/components/Background.tsx` | **Thiếu** file video trong tree Laravel (`public/video/` và `public/legacy/video/` đều không có). Owner cung cấp bản gốc + license/hash, hoặc chốt thiết kế fallback có thật cho P03.01/auth UI. |
| `/img/videoPoster.png` trong `modules/register/components/Background.tsx` | **Thiếu** poster PNG (không có trong `public/`/`public/legacy/`). Owner cung cấp asset đúng nguồn + license/hash hoặc chốt fallback, không đổi logo/ảnh khác tùy tiện. |
| `@/public/img/DHL.webp` trong `modules/checkout/components/CheckoutForm.tsx` | **Thiếu** ảnh logo DHL trong repo; chỉ là import legacy, **không** là xác nhận sẽ tích hợp hãng vận chuyển DHL. Owner quyết định có dùng/được phép dùng hình hay bỏ chi tiết theo yêu cầu checkout hiện hành. |
| Ảnh product/variant cho card/gallery và fallback | **Chưa có bộ ảnh catalog local** ngoài 5 ảnh hero/collection. `modules/products/components/Product.tsx` và `modules/productDetails/components/ProductGallery.tsx` đọc URL/hash ảnh từ backend cũ; không xem đó là file asset local hoặc endpoint Laravel. Owner chốt nguồn hợp pháp, storage/URL và fallback **có thật** theo P03.03/P03.04/P05.01, không tự tải Nike/remote, không giả sản phẩm. |

## Ranh giới port

- **Có thể port phần trình bày:** login/register, catalog/list/detail, cart, checkout, account/order, admin và component chung từ các reference ở bảng trên, theo phase/gate canonical.
- **Không port module security Oracle hoặc thanh toán Stripe/Strapi** như một màn Cena chuẩn: ADR-002 loại Oracle/SQL Server/Next/JWT/Recoil/Stripe runtime; snapshot 75 tệp không cung cấp contract/backend hợp lệ cho các tích hợp này. Chỉ theo auth/CSRF/Policy, PaymentService/VNPay/COD và API Laravel khi owner merge contract thật. Không tạo màn success/badge/endpoint giả.
- EXT-003 **chỉ là bản đồ tài liệu**; không thay `docs/reference/legacy-ui/`, `public/legacy/`, CSS global, homepage, manifest, routes, dependencies hoặc nhật ký chung. Không thực thi build/browser/MySQL trong tài liệu này; nghiệm thu màn hình, focus/keyboard, ảnh/video và pixel-level thuộc các task UI sau gate.
