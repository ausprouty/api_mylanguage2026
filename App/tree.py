import os

def show_tree(start_path, max_depth=3, prefix=""):
    for root, dirs, files in os.walk(start_path):
        depth = root[len(start_path):].count(os.sep)
        if depth > max_depth:
            # don't go deeper than max_depth
            continue

        indent = " " * 4 * depth
        print(f"{indent}{os.path.basename(root)}/")
        subindent = " " * 4 * (depth + 1)

        for f in files:
            print(f"{subindent}{f}")

        # prevent descending further if already at max depth
        dirs[:] = [d for d in dirs if depth < max_depth]

# Run it from your project root
if __name__ == "__main__":
    project_root = os.path.dirname(__file__)  # folder where script is saved
    show_tree(project_root, max_depth=3)
