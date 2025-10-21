import os
from bs4 import BeautifulSoup

# Directory containing the files
directory = r"c:\ampp82\htdocs\api_mylanguage\Resources\bibles\wordproject"

# Function to check <h3> tags in a file
def check_h3_tags(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as file:
            content = file.read()
            soup = BeautifulSoup(content, 'html.parser')
            h3_tags = soup.find_all('h3')
            if len(h3_tags) > 1:
                print(f"File: {filepath} has {len(h3_tags)} <h3> tags.")
    except Exception as e:
        print(f"Error processing file {filepath}: {e}")

# Traverse the directory and check each file
for root, _, files in os.walk(directory):
    for filename in files:
        if filename.endswith(('.html', '.htm')):  # Process only HTML files
            filepath = os.path.join(root, filename)
            check_h3_tags(filepath)
