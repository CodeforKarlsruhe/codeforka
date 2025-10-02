import cv2
import numpy as np
from PIL import Image
from skimage import io
import svgwrite
import matplotlib.pyplot as plt
import sys


def load_image(image_path):
    # Load the image using Pillow to support WEBP format
    pil_image = Image.open(image_path).convert('L')  # Convert to grayscale
    image = np.array(pil_image)
    return image

def threshold_image(image, threshold=128):
    _, thresh = cv2.threshold(image, threshold, 255, cv2.THRESH_BINARY)
    return thresh

def invert_image(image):
    return cv2.bitwise_not(image)

def vectorize_image(image):
    contours, hierarchy = cv2.findContours(image, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_SIMPLE)
    return contours, hierarchy

def display_image(image, title):
    plt.imshow(image, cmap='gray')
    plt.title(title)
    plt.axis('off')
    plt.show()

def display_contours(image, contours, title):
    contour_image = np.zeros_like(image)
    cv2.drawContours(contour_image, contours, -1, (255, 255, 255), 1)
    plt.imshow(contour_image, cmap='gray')
    plt.title(title)
    plt.axis('off')
    plt.show()

def display_vectorized_image(contours, width, height):
    fig, ax = plt.subplots()
    ax.set_xlim(0, width)
    ax.set_ylim(height, 0)  # Invert the y-axis to match image coordinates
    for contour in contours:
        points = np.array(contour).reshape(-1, 2)
        ax.plot(points[:, 0], points[:, 1], color='#000')
    plt.title('Vectorized Image')
    plt.axis('off')
    plt.show()

def export_svg(contours, svg_path, width, height, background_color='#fff', line_color='#000'):
    dwg = svgwrite.Drawing(svg_path, profile='tiny', size=(width, height))
    if background_color != 'none':
        dwg.add(dwg.rect(insert=(0, 0), size=(width, height), fill=background_color))
    for contour in contours:
        points = [(int(point[0][0]), int(point[0][1])) for point in contour]
        if len(points) > 2:  # Ensure there are enough points to form a polygon
            poly = dwg.polygon(points, fill=line_color, fill_opacity = .4, stroke=line_color, stroke_width=5)
            dwg.add(poly)
                
    dwg.save()

def process_image(image_path):
    image = load_image(image_path)

    image_base = image_path.split(".")[0]
    # Display the imported image
    #display_image(image, 'Imported Image')
    
    thresh_image = threshold_image(image,100)
    
    # Display the thresholded image
    #display_image(thresh_image, 'Thresholded Image')
    
    inverted_image = invert_image(thresh_image)
    
    # Display the inverted thresholded image
    #display_image(inverted_image, 'Inverted Thresholded Image')
    
    contours, hierarchy = vectorize_image(thresh_image)
    #contours, hierarchy = vectorize_image(image)
    inverted_contours, _ = vectorize_image(inverted_image)
    
    height, width = image.shape

    # Display contours on the original thresholded image
    #display_contours(thresh_image, contours, 'Contours on Thresholded Image')
    
    # Display contours on the inverted thresholded image
    #display_contours(inverted_image, inverted_contours, 'Contours on Inverted Thresholded Image')

    # Display the vectorized image
    #display_vectorized_image(contours, width, height)
    
    export_svg(contours, f'{image_base}_black_on_white.svg', width, height, background_color='#fff', line_color='#000')
    export_svg(inverted_contours, f'{image_base}_white_on_black.svg', width, height, background_color='#000', line_color='#fff')
    export_svg(contours, f'{image_base}_black_on_transparent.svg', width, height, background_color='none', line_color='#000')
    export_svg(inverted_contours, f'{image_base}_white_on_transparent.svg', width, height, background_color='none', line_color='#fff')

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Provide image name")
        sys.exit()
    process_image(sys.argv[1])
