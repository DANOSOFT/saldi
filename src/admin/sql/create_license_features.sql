-- Feature Licensing System
-- Run this in the MASTER database to create the license_features table

CREATE TABLE IF NOT EXISTS license_features (
    id SERIAL PRIMARY KEY,
    regnskab_id INT NOT NULL,           -- Links to regnskab.id
    feature_key VARCHAR(50) NOT NULL,   -- 'booking', 'lager', 'kreditor'
    enabled BOOLEAN DEFAULT false,
    expires_at DATE NULL,               -- Optional expiration date
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(regnskab_id, feature_key)
);

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_license_features_regnskab ON license_features(regnskab_id);
CREATE INDEX IF NOT EXISTS idx_license_features_feature ON license_features(feature_key);

-- Seed initial features for all existing regnskaber (all enabled by default)
INSERT INTO license_features (regnskab_id, feature_key, enabled) 
SELECT id, 'booking', true FROM regnskab
ON CONFLICT (regnskab_id, feature_key) DO NOTHING;

INSERT INTO license_features (regnskab_id, feature_key, enabled) 
SELECT id, 'lager', true FROM regnskab
ON CONFLICT (regnskab_id, feature_key) DO NOTHING;

INSERT INTO license_features (regnskab_id, feature_key, enabled) 
SELECT id, 'kreditor', true FROM regnskab
ON CONFLICT (regnskab_id, feature_key) DO NOTHING;
