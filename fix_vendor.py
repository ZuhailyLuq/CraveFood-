import re

with open('c:\\xampp1\\htdocs\\CraveFood\\api\\VendorProfileEdit.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = r'<input type="file" name="ImageFile" id="imageFileInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage\(this\)">(?:\s|.)*?/\* ── Navbar active link ── \*/'

replacement = """<input type="file" name="ImageFile" id="imageFileInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                </label>
                <div class="image-preview-box" id="imagePreview" style="display:none;">
                    <img id="previewImg" src="#" alt="Preview">
                </div>
            </div>

            <div class="form-group">
                <label>Pin your Location on the Map</label>
                <div id="vendorMap" style="height: 300px; width: 100%; border-radius: 12px; border: 2px solid #e8e8e8; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); z-index: 1;"></div>
                <div style="display: flex; gap: 10px;">
                    <input type="text" class="modern-input" id="Latitude" name="Latitude" value="<?php echo htmlspecialchars((string)($vendor['Latitude'] ?? '')); ?>" placeholder="Latitude" readonly style="background: #f5f5f5; color: #888; cursor: not-allowed;">
                    <input type="text" class="modern-input" id="Longitude" name="Longitude" value="<?php echo htmlspecialchars((string)($vendor['Longitude'] ?? '')); ?>" placeholder="Longitude" readonly style="background: #f5f5f5; color: #888; cursor: not-allowed;">
                </div>
                <small style="display:block; margin-top:8px; color:#888; font-size:0.85rem;">Click anywhere on the map to pin your exact location. You can drag the pin to adjust it.</small>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px;">Save Profile Updates</button>
        </form>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    /* ── Navbar active link ── */"""

content = re.sub(target, replacement, content)

with open('c:\\xampp1\\htdocs\\CraveFood\\api\\VendorProfileEdit.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Replacement done!")
