import os

def rename_files(directory):
    for root, _, files in os.walk(directory):
        for file in files:
            if file == "jVideoStructured.json":
                old_path = os.path.join(root, file)
                new_path = os.path.join(root, "jvideoStructured.json")
                try:
                    os.rename(old_path, new_path)
                    print(f"Renamed: {old_path} -> {new_path}")
                except Exception as e:
                    print(f"Error renaming {old_path}: {e}")

if __name__ == "__main__":
    directory = "c:/ampp82/htdocs/api_mylanguage/Resources/translations/languages"  # Replace with the actual directory path
    if os.path.exists(directory) and os.path.isdir(directory):
        rename_files(directory)
    else:
        print("Invalid directory path.")
