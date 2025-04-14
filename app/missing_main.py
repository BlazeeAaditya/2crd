import os

def get_folder_structure(root_dir, indent=""):
    try:
        items = sorted(os.listdir(root_dir))  # Sort to maintain consistent order
    except PermissionError:
        print(indent + "[Permission Denied]")
        return
    
    for item in items:
        path = os.path.join(root_dir, item)
        if os.path.isdir(path):
            print(indent + f"[DIR] {item}")
            get_folder_structure(path, indent + "  ")  # Recursively call for subdirectories
        else:
            print(indent + f"  {item}")

if __name__ == "__main__":
    folder_path = input("Enter the folder path: ").strip()
    if os.path.exists(folder_path):
        print(f"\nFolder Structure of: {folder_path}\n")
        get_folder_structure(folder_path)
    else:
        print("Error: Folder path does not exist.")
