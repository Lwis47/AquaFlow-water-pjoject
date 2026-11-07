PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    category TEXT NOT NULL CHECK (category IN ('Domestic', 'Commercial', 'Industrial')),
    telephone TEXT NOT NULL,
    email TEXT,
    address TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    service_group TEXT NOT NULL,
    description TEXT NOT NULL,
    base_price INTEGER,
    pricing_model TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1))
);

CREATE TABLE IF NOT EXISTS applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference TEXT NOT NULL UNIQUE,
    client_id INTEGER NOT NULL,
    service_id INTEGER,
    drilling_type TEXT,
    pump_type TEXT,
    location TEXT NOT NULL,
    depth_metres REAL,
    tank_height_metres REAL,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'Review'
        CHECK (status IN ('Draft', 'Review', 'Survey booked', 'Approved', 'Completed')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (client_id) REFERENCES clients (id),
    FOREIGN KEY (service_id) REFERENCES services (id)
);

CREATE TABLE IF NOT EXISTS quotations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference TEXT NOT NULL UNIQUE,
    client_id INTEGER,
    application_id INTEGER,
    category TEXT NOT NULL,
    drilling_type TEXT NOT NULL,
    pump_type TEXT NOT NULL,
    depth_metres REAL NOT NULL DEFAULT 0,
    tank_height_metres REAL NOT NULL DEFAULT 0,
    survey_fee INTEGER NOT NULL,
    authority_fee INTEGER NOT NULL,
    drilling_fee INTEGER NOT NULL,
    pump_fee INTEGER NOT NULL,
    depth_fee INTEGER NOT NULL,
    pipe_type TEXT,
    pipe_diameter_inches REAL NOT NULL DEFAULT 0,
    pipe_length_metres REAL NOT NULL DEFAULT 0,
    outlets INTEGER NOT NULL DEFAULT 0,
    pipe_rate_per_metre REAL NOT NULL DEFAULT 0,
    outlet_rate REAL NOT NULL DEFAULT 0,
    plumbing_fee INTEGER NOT NULL DEFAULT 0,
    subtotal INTEGER NOT NULL,
    tax INTEGER NOT NULL,
    total INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'Draft'
        CHECK (status IN ('Draft', 'Sent', 'Accepted', 'Expired')),
    valid_until TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (client_id) REFERENCES clients (id),
    FOREIGN KEY (application_id) REFERENCES applications (id)
);

CREATE INDEX IF NOT EXISTS idx_applications_client ON applications (client_id);
CREATE INDEX IF NOT EXISTS idx_applications_status ON applications (status);
CREATE INDEX IF NOT EXISTS idx_quotations_client ON quotations (client_id);
