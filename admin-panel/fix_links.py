
import re

file_path = '/Users/amir/Downloads/ertah/admin-panel/includes/header.php'

with open(file_path, 'r') as f:
    content = f.read()

# Fix the broken empty echos
# Case 1: index.php
content = content.replace('href="<?php echo ; ?>index.php"', 'href="<?php echo $root; ?>index.php"')

# Case 2: all other pages (users.php, providers.php, etc)
# Since they all look like href="<?php echo ; ?>filename.php" now, and only index.php needs root
# I can blindly replace the rest with $pages, BUT I must ensure I don't double replace index.php if I did it above.
# Actually, let's clearer.

# Regex to find href="<?php echo ; ?>SOMETHING.php"
def replacer(match):
    filename = match.group(1)
    if filename == 'index.php':
        return 'href="<?php echo $root; ?>index.php"'
    else:
        return f'href="<?php echo $pages; ?>{filename}"'

content = re.sub(r'href="\<\?php echo ; \?\>([^"]+)"', replacer, content)

# Check specific hardcoded missed ones if any
# Also fix profile dropdown which might have been affected if it used similar pattern, but I used $profileLink vars there so it might be fine.
# Let's double check profile link manually in the file view later.

with open(file_path, 'w') as f:
    f.write(content)

print("Links fixed successfully.")
