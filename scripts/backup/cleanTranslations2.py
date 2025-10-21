import os
import re

# Set to True to apply changes, or False to preview
WRITE_CHANGES = True

# Root folder to start scanning
ROOT_DIR = "c:\\ampp82\\htdocs\\api_mylanguage\\Resources\\translations\\new"

#

# Replacement patterns
REPLACEMENTS = {
    r'"lookback"': '"look_back"',
    r'"lookup"': '"look_up"',
    r'"lookforward"': '"look_forward"'
}


def process_file(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as file:
            content = file.read()
    except Exception as e:
        print(f"❌ Failed to read {filepath}: {e}")
        return
    new_content = content
    for pattern, replacement in REPLACEMENTS.items():
        new_content = re.sub(pattern, replacement, new_content)

    if new_content != content:
        print(f"✅ Changes in: {filepath}")
        if WRITE_CHANGES:
            with open(filepath, 'w', encoding='utf-8') as file:
                file.write(new_content)
        else:
            print("🔍 Preview only - set WRITE_CHANGES = True to apply changes")

def traverse_folder(path):
    for root, _, files in os.walk(path):
        for file in files:
            full_path = os.path.join(root, file)

            if file.endswith(( '.json')):  # Add extensions as needed
                print(f"🔍 Processing file: {full_path}")
                process_file(os.path.join(root, file))

if __name__ == '__main__':
    traverse_folder(ROOT_DIR)
