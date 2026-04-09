# Backend POS - Migration SQL (Contoh Awal)

Catatan:
- Contoh SQL ini bersifat ringkas untuk rancangan awal.
- Gunakan MySQL 8+ atau PostgreSQL 13+.
- Semua tabel bisnis memiliki tenant_id.
- FK transaksi gunakan ON DELETE RESTRICT.

## 1) Tenants, Outlets, Registers
```sql
CREATE TABLE tenants (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE outlets (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(50) NOT NULL,
  timezone VARCHAR(50) NULL,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_outlets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_outlets_tenant_code ON outlets(tenant_id, code);
CREATE INDEX ix_outlets_tenant ON outlets(tenant_id);

CREATE TABLE registers (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  outlet_id BIGINT NOT NULL,
  name VARCHAR(150) NOT NULL,
  device_uid VARCHAR(100) NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_registers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_registers_outlet FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_registers_device_uid ON registers(device_uid);
CREATE INDEX ix_registers_outlet ON registers(outlet_id);
```

## 2) Users, Roles, Permissions
```sql
CREATE TABLE users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_users_tenant_email ON users(tenant_id, email);
CREATE INDEX ix_users_tenant ON users(tenant_id);

CREATE TABLE roles (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(100) NOT NULL,
  CONSTRAINT fk_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_roles_tenant_name ON roles(tenant_id, name);

CREATE TABLE permissions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  perm_key VARCHAR(150) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
);

CREATE TABLE user_roles (
  user_id BIGINT NOT NULL,
  role_id BIGINT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

CREATE TABLE role_permissions (
  role_id BIGINT NOT NULL,
  permission_id BIGINT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_role_permissions_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE RESTRICT
);
```

## 3) Products, Variants, Categories
```sql
CREATE TABLE categories (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(150) NOT NULL,
  parent_id BIGINT NULL,
  CONSTRAINT fk_categories_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
);
CREATE INDEX ix_categories_tenant ON categories(tenant_id);
CREATE INDEX ix_categories_parent ON categories(parent_id);

CREATE TABLE products (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(200) NOT NULL,
  sku VARCHAR(100) NULL,
  barcode VARCHAR(100) NULL,
  status VARCHAR(20) NOT NULL,
  CONSTRAINT fk_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_products_tenant_sku ON products(tenant_id, sku);
CREATE INDEX ix_products_tenant ON products(tenant_id);

CREATE TABLE product_variants (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  product_id BIGINT NOT NULL,
  sku VARCHAR(100) NULL,
  barcode VARCHAR(100) NULL,
  attrs_json JSON NULL,
  CONSTRAINT fk_variants_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_variants_tenant_sku ON product_variants(tenant_id, sku);
CREATE INDEX ix_variants_product ON product_variants(product_id);
```

## 4) Inventory & Stock Movements
```sql
CREATE TABLE inventory (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  outlet_id BIGINT NOT NULL,
  product_variant_id BIGINT NOT NULL,
  stock_on_hand INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_inventory_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_outlet FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_inventory_outlet_variant ON inventory(outlet_id, product_variant_id);
CREATE INDEX ix_inventory_tenant ON inventory(tenant_id);

CREATE TABLE stock_movements (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  outlet_id BIGINT NOT NULL,
  product_variant_id BIGINT NOT NULL,
  qty INT NOT NULL,
  movement_type VARCHAR(30) NOT NULL,
  ref_type VARCHAR(30) NOT NULL,
  ref_id BIGINT NOT NULL,
  created_by BIGINT NULL,
  created_at TIMESTAMP NULL,
  CONSTRAINT fk_sm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_outlet FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
);
CREATE INDEX ix_sm_tenant_outlet_variant_time ON stock_movements(tenant_id, outlet_id, product_variant_id, created_at);
CREATE INDEX ix_sm_ref ON stock_movements(ref_type, ref_id);
```

## 5) Sales & Payments
```sql
CREATE TABLE sales (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  outlet_id BIGINT NOT NULL,
  register_id BIGINT NOT NULL,
  created_by BIGINT NOT NULL,
  customer_id BIGINT NULL,
  status VARCHAR(20) NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_sales_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_outlet FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_register FOREIGN KEY (register_id) REFERENCES registers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);
CREATE INDEX ix_sales_tenant_outlet_time ON sales(tenant_id, outlet_id, created_at);
CREATE INDEX ix_sales_register ON sales(register_id);
CREATE INDEX ix_sales_created_by ON sales(created_by);

CREATE TABLE sale_items (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  sale_id BIGINT NOT NULL,
  product_variant_id BIGINT NOT NULL,
  qty INT NOT NULL,
  price DECIMAL(14,2) NOT NULL,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sale_items_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
);
CREATE INDEX ix_sale_items_sale_variant ON sale_items(sale_id, product_variant_id);

CREATE TABLE payment_methods (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(50) NOT NULL,
  method_type VARCHAR(30) NOT NULL,
  CONSTRAINT fk_payment_methods_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX ux_payment_methods_tenant_name ON payment_methods(tenant_id, name);

CREATE TABLE payments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  sale_id BIGINT NOT NULL,
  payment_method_id BIGINT NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  ref_code VARCHAR(100) NULL,
  CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT
);
CREATE INDEX ix_payments_sale ON payments(sale_id);
```

## 6) Purchases & Suppliers
```sql
CREATE TABLE suppliers (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL,
  CONSTRAINT fk_suppliers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
);
CREATE INDEX ix_suppliers_tenant ON suppliers(tenant_id);

CREATE TABLE purchases (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  outlet_id BIGINT NOT NULL,
  supplier_id BIGINT NOT NULL,
  status VARCHAR(20) NOT NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  CONSTRAINT fk_purchases_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_purchases_outlet FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
);
CREATE INDEX ix_purchases_tenant_outlet_time ON purchases(tenant_id, outlet_id, created_at);

CREATE TABLE purchase_items (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  purchase_id BIGINT NOT NULL,
  product_variant_id BIGINT NOT NULL,
  qty INT NOT NULL,
  cost DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE RESTRICT,
  CONSTRAINT fk_purchase_items_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
);
CREATE INDEX ix_purchase_items_purchase_variant ON purchase_items(purchase_id, product_variant_id);
```

## 7) Audit Logs
```sql
CREATE TABLE audit_logs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  action VARCHAR(50) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT NOT NULL,
  payload_json JSON NULL,
  created_at TIMESTAMP NULL,
  CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);
CREATE INDEX ix_audit_tenant_user_time ON audit_logs(tenant_id, user_id, created_at);
CREATE INDEX ix_audit_entity ON audit_logs(entity_type, entity_id);
```

Lanjutkan menambah tabel lain dengan pola yang sama.
