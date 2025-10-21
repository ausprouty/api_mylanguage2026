import os

# Define the directory containing language folders
languages_directory = "c:\\ampp82\\htdocs\\api_mylanguage\\Resources\\translations\\languages"

# List of filenames to delete
files_to_delete = {"dbs.json", "lead.json", "life.json", "video.json"}

def delete_selected_files():
    """Deletes specified JSON files in subdirectories of languages directory."""
    if not os.path.exists(languages_directory):
        print(f"Directory '{languages_directory}' not found.")
        return

    for folder in os.listdir(languages_directory):
        folder_path = os.path.join(languages_directory, folder)

        # Ensure it's a directory
        if os.path.isdir(folder_path):
            for file_name in files_to_delete:
                file_path = os.path.join(folder_path, file_name)
                
                if os.path.exists(file_path):
                    os.remove(file_path)
                    print(f"Deleted: {file_path}")
                else:
                    print(f"Not found (skipped): {file_path}")

# Run the deletion function
if __name__ == "__main__":
    delete_selected_files()
