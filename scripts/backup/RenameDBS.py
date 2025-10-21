import os

def rename_files_in_folder(folder_path):
    try:
        # List all files in the folder
        for filename in os.listdir(folder_path):
            new_filename = filename

            # Check if the filename starts with "DBS"
            if filename.startswith("DBS"):
                # Generate the new filename by replacing "DBS" with "Dbs"
                new_filename = "Dbs" + filename[3:]

            # Check if the filename contains "Leadership"
            if "Leadership" in filename:
                # Replace "Leadership" with "Lead"
                new_filename = new_filename.replace("Leadership", "Lead")

            # Generate full paths
            old_file_path = os.path.join(folder_path, filename)
            new_file_path = os.path.join(folder_path, new_filename)

            # Rename the file if the name has changed
            if filename != new_filename:
                os.rename(old_file_path, new_file_path)
                print(f"Renamed: {filename} -> {new_filename}")

    except Exception as e:
        print(f"An error occurred: {e}")

# Replace with your folder path
folder_path = r"C:/ampp82/htdocs/api_mylanguage/Resources/qrcodes"
rename_files_in_folder(folder_path)
