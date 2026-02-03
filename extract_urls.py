import json
import re

input_file = "C:\\Users\\coldh\\.gemini\\antigravity\\scratch\\gallery_metadata_local.json"
output_file = "C:\\Users\\coldh\\.gemini\\antigravity\\scratch\\urls.txt"

try:
    with open(input_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
        
    urls = []
    # Data is a list of [id, dict] or similar structure based on sample
    # Sample: [ [2, {...}], ... ]
    
    for item in data:
        if isinstance(item, list) and len(item) > 1 and isinstance(item[1], dict):
            info = item[1]
            if 'url' in info:
                urls.append(info['url'])
                
    # Deduplicate and sort
    urls = sorted(list(set(urls)))
    
    with open(output_file, 'w', encoding='utf-8') as f:
        for url in urls:
            f.write(url + '\n')
            
    print(f"Extracted {len(urls)} URLs to {output_file}")
    
except Exception as e:
    print(f"Error: {e}")
