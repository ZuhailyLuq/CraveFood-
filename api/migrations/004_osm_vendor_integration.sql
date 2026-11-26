-- ============================================================
-- Migration 004: OSM / Overpass vendor integration
-- Run once in Supabase SQL editor or via execute_sql MCP tool
-- ============================================================

-- 1. Extend vendor table with OSM & external-source columns
ALTER TABLE vendor
  ADD COLUMN IF NOT EXISTS "VendorType"     VARCHAR(20)   DEFAULT 'registered',
  ADD COLUMN IF NOT EXISTS "ExternalID"     VARCHAR(50)   DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "ExternalSource" VARCHAR(20)   DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "ExternalURL"    TEXT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "IsVerified"     BOOLEAN       DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS "LastSynced"     TIMESTAMPTZ   DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "Categories"     TEXT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "Phone"          VARCHAR(30)   DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "Website"        TEXT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "DietaryInfo"    TEXT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS "Amenity"        VARCHAR(30)   DEFAULT NULL;

-- Make sure existing vendors are marked as registered
UPDATE vendor SET "VendorType" = 'registered', "IsVerified" = TRUE
  WHERE "VendorType" IS NULL OR "VendorType" = '';

-- 2. OSM raw data cache (avoid hitting Overpass on every request)
CREATE TABLE IF NOT EXISTS vendor_osm_cache (
  "CacheID"   SERIAL PRIMARY KEY,
  "OsmID"     BIGINT       NOT NULL,
  "OsmType"   VARCHAR(10)  NOT NULL,  -- 'node' | 'way'
  "RawTags"   JSONB,
  "CenterLat" DECIMAL(10,8),
  "CenterLon" DECIMAL(11,8),
  "Region"    VARCHAR(60),
  "FetchedAt" TIMESTAMPTZ  DEFAULT NOW(),
  UNIQUE ("OsmType", "OsmID")
);

-- Index for fast lookup
CREATE INDEX IF NOT EXISTS idx_osm_cache_region ON vendor_osm_cache ("Region");

-- 3. Vendor claim requests (OSM vendor owner can claim their profile)
CREATE TABLE IF NOT EXISTS vendor_claim_requests (
  "ClaimID"   SERIAL PRIMARY KEY,
  "VendorID"  INTEGER      REFERENCES vendor("VendorID") ON DELETE CASCADE,
  "Email"     VARCHAR(255) NOT NULL,
  "Message"   TEXT,
  "Status"    VARCHAR(20)  DEFAULT 'pending',  -- pending | approved | rejected
  "CreatedAt" TIMESTAMPTZ  DEFAULT NOW()
);

-- 4. Index for imported vendor lookup
CREATE UNIQUE INDEX IF NOT EXISTS idx_vendor_external
  ON vendor ("ExternalSource", "ExternalID")
  WHERE "ExternalID" IS NOT NULL;

