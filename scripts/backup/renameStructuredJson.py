import os

# Define the directory containing language folders
languages_directory = "c:\\ampp82\\htdocs\\api_mylanguage\\Resources\\translations\\languages"
def rename_files():
    """Renames all files ending in 'Structured.json' to just '.json'."""
    if not os.path.exists(languages_directory):
        print(f"Directory '{languages_directory}' not found.")
        return

    for folder in os.listdir(languages_directory):
        folder_path = os.path.join(languages_directory, folder)

        # Ensure it's a directory
        if os.path.isdir(folder_path):
            for file in os.listdir(folder_path):
                if file.endswith("Structured.json"):
                    old_path = os.path.join(folder_path, file)
                    new_path = os.path.join(folder_path, file.replace("Structured.json", ".json"))
                    
                    os.rename(old_path, new_path)
                    print(f"Renamed: {old_path} → {new_path}")

# Run the renaming function
if __name__ == "__main__":
    rename_files()
