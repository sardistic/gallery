import requests
from bs4 import BeautifulSoup
import json
import os
import time
from urllib.parse import urljoin

# Configuration
USERNAME = "coldhunter"
OUTPUT_DIR = "downloaded_art"
DELAY_BETWEEN_REQUESTS = 2  # seconds to avoid hammering the server

# Create output directory
os.makedirs(OUTPUT_DIR, exist_ok=True)

def get_gallery_page(offset=0):
    """Fetch a page of the gallery"""
    url = f"https://www.deviantart.com/{USERNAME}/gallery/all?offset={offset}"
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    }
    
    response = requests.get(url, headers=headers)
    response.raise_for_status()
    return response.text

def extract_image_data(html):
    """Extract image URLs and metadata from the gallery page HTML"""
    soup = BeautifulSoup(html, 'html.parser')
    
    # DeviantArt often embeds data in JSON within script tags
    images = []
    
    # Look for deviation data in script tags
    for script in soup.find_all('script', type='application/ld+json'):
        try:
            data = json.loads(script.string)
            if isinstance(data, dict) and 'image' in data:
                images.append({
                    'url': data.get('image'),
                    'title': data.get('name', 'untitled'),
                    'description': data.get('description', '')
                })
        except:
            pass
    
    # Also look for direct image links
    for img in soup.find_all('img', class_=lambda x: x and 'deviation' in x.lower()):
        src = img.get('src')
        if src and 'https' in src:
            images.append({
                'url': src,
                'title': img.get('alt', 'untitled'),
                'description': ''
            })
    
    return images

def download_image(url, filename):
    """Download an image from URL to file"""
    try:
        response = requests.get(url, stream=True)
        response.raise_for_status()
        
        with open(filename, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)
        
        print(f"✓ Downloaded: {filename}")
        return True
    except Exception as e:
        print(f"✗ Failed to download {url}: {e}")
        return False

def main():
    print(f"Starting scrape of {USERNAME}'s DeviantArt gallery...")
    print(f"Output directory: {OUTPUT_DIR}\n")
    
    offset = 0
    page_num = 1
    total_downloaded = 0
    
    while True:
        print(f"\n--- Page {page_num} (offset={offset}) ---")
        
        try:
            html = get_gallery_page(offset)
            images = extract_image_data(html)
            
            if not images:
                print("No more images found. Scraping complete!")
                break
            
            print(f"Found {len(images)} images on this page")
            
            for idx, img_data in enumerate(images):
                # Sanitize filename
                title = img_data['title'][:50].replace('/', '_').replace('\\', '_')
                ext = img_data['url'].split('.')[-1].split('?')[0]
                filename = os.path.join(OUTPUT_DIR, f"{offset+idx:04d}_{title}.{ext}")
                
                # Skip if already downloaded
                if os.path.exists(filename):
                    print(f"⊘ Skipping (already exists): {title}")
                    continue
                
                if download_image(img_data['url'], filename):
                    total_downloaded += 1
                
                # Save metadata
                metadata_file = filename + '.json'
                with open(metadata_file, 'w', encoding='utf-8') as f:
                    json.dump(img_data, f, indent=2)
                
                time.sleep(0.5)  # Small delay between downloads
            
            offset += len(images)
            page_num += 1
            
            # Delay between pages
            print(f"\nWaiting {DELAY_BETWEEN_REQUESTS}s before next page...")
            time.sleep(DELAY_BETWEEN_REQUESTS)
            
        except Exception as e:
            print(f"Error on page {page_num}: {e}")
            break
    
    print(f"\n{'='*50}")
    print(f"Scraping complete! Downloaded {total_downloaded} images.")
    print(f"Files saved to: {os.path.abspath(OUTPUT_DIR)}")

if __name__ == "__main__":
    main()
