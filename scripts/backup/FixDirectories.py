import os
import shutil

def clean_directory_structure(base_directory):
    """
    Moves files from duplicate subdirectories (e.g., af/af) to their parent
    directory (e.g., af) and removes the duplicate subdirectory.
    """
    for root, dirs, files in os.walk(base_directory):
        for dir_name in dirs:
            # Check if a subdirectory has the same name as its parent
            parent_dir = os.path.basename(root)
            if dir_name == parent_dir:
                nested_dir = os.path.join(root, dir_name)
                
                # Move files from nested directory to parent
                for file_name in os.listdir(nested_dir):
                    file_path = os.path.join(nested_dir, file_name)
                    new_path = os.path.join(root, file_name)
                    shutil.move(file_path, new_path)
                    print(f"Moved: {file_path} -> {new_path}")
                
                # Remove the empty nested directory
                os.rmdir(nested_dir)
                print(f"Removed empty directory: {nested_dir}")

# Set the base directory you want to clean up
base_directory = r'c:\ampp82\htdocs\api_mylanguage\Resources\bibles\wordproject'
clean_directory_structure(base_directory)
