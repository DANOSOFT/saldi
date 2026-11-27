-- Create API keys table
CREATE TABLE IF NOT EXISTS api_keys (
    id SERIAL PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    database VARCHAR(255) NOT NULL,
    description TEXT,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    created_by VARCHAR(255),
    CONSTRAINT unique_api_key UNIQUE (api_key)
);

-- Create index on api_key for faster lookups
CREATE INDEX IF NOT EXISTS idx_api_keys_api_key ON api_keys(api_key);

-- Create function to generate API key
CREATE OR REPLACE FUNCTION generate_api_key() 
RETURNS VARCHAR AS $$
DECLARE
    new_key VARCHAR;
BEGIN
    -- Generate a random 32-character hex string
    new_key := encode(gen_random_bytes(16), 'hex');
    RETURN new_key;
END;
$$ LANGUAGE plpgsql; 