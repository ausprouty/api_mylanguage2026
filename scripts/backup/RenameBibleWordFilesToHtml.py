import os

def rename_files_to_html(directory):
    """
    Rename all files with the .htm extension to .html in the given directory and its subdirectories.
    """
    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith('.htm') and not file.endswith('.html'):
                old_path = os.path.join(root, file)
                new_path = os.path.join(root, file[:-4] + '.html')  # Replace .htm with .html
                os.rename(old_path, new_path)
                print(f"Renamed: {old_path} -> {new_path}")

# Set the directory you want to process
directory_path = r'c:\ampp82\htdocs\api_mylanguage\Resources\bibles\wordproject'
rename_files_to_html(directory_path)
