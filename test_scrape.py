import requests
import re
import json

url = "https://www.deviantart.com/coldhunter/art/Old-School-874644325"
headers = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
    "Accept-Language": "en-US,en;q=0.5",
    "Referer": "https://www.deviantart.com/"
}

try:
    print(f"Fetching {url}...")
    r = requests.get(url, headers=headers, timeout=10)
    print(f"Status Code: {r.status_code}")
    
    if r.status_code == 200:
        html = r.text
        # dump first 500 lines or search for keywords
        print("Searching for Camera Data...")
        
        # Regex for common metrics
        patterns = [
            r'CameraModel":"([^"]+)"',
            r'Lens":"([^"]+)"', 
            r'ISO":"([^"]+)"',
            r'Aperture":"([^"]+)"',
            r'FocalLength":"([^"]+)"',
            r'>Camera<.*?<div>(.*?)<\/div>',
            r'>Lens<.*?<div>(.*?)<\/div>',
            r'>ISO<.*?<div>(.*?)<\/div>'
        ]
        
        found = False
        for p in patterns:
            matches = re.findall(p, html, re.DOTALL | re.IGNORECASE)
            if matches:
                print(f"Match for pattern {p}: {matches}")
                found = True
        
        if not found:
            print("No simple regex matches found.")
            # check for initial state
            if "INITIAL_STATE" in html:
                print("Found INITIAL_STATE blob.")
                
    else:
        print("Failed to fetch page.")

except Exception as e:
    print(f"Error: {e}")
