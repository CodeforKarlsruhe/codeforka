from PIL import Image
import os

WIDTH = 800

# Input and output folder paths
input_folder = '../static/projects/karlsruhe/'
output_folder = '../static/projects/karlsruhe/thumbnails/'

# Create the output folder if it doesn't exist
if not os.path.exists(output_folder):
    os.makedirs(output_folder)

# Iterate through all images in the input folder
files = os.listdir(input_folder)
#print(files)
for filename in files:
    if filename.endswith(('.png', '.jpg', '.jpeg', '.gif')):
        print(filename)
        # Open the image
        img_path = os.path.join(input_folder, filename)
        img = Image.open(img_path)
        
        # Calculate new dimensions while maintaining aspect ratio
        width, height = img.size
        aspect_ratio = width / height
        new_width = WIDTH if aspect_ratio >= 1 else int(WIDTH * aspect_ratio)
        new_height = int(new_width / aspect_ratio)

        #print(img_path,width,height)
        
        # Resize the image
        img.thumbnail((new_width, new_height))
        #print(img.width,img.height)
        
        # Save the resized image in the output folder
        output_path = os.path.join(output_folder, filename)
        img.save(output_path)
