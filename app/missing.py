import os
import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin

# Base URL to crawl
FILE_SOURCE_URL = "https://2crd.cc"

# Create a requests session (reuse connections for speed)
session = requests.Session()

def get_links(url):
    """Fetch and parse links from a given URL."""
    try:
        response = session.get(url, timeout=10)
        print(f"Fetching {url}... Status: {response.status_code}")
        
        if response.status_code == 200:
            soup = BeautifulSoup(response.text, "html.parser")
            links = [a["href"] for a in soup.find_all("a", href=True)]
            print(f"Links found: {links}")  # Debugging
            return links
        else:
            print(f"Failed to fetch {url} (Status {response.status_code})")
            return []
    except requests.RequestException as e:
        print(f"Error fetching {url}: {e}")
        return []

def get_all_files(base_url):
    """Recursively find all .php files on the site."""
    to_check = [base_url]
    all_files = set()

    while to_check:
        current_url = to_check.pop()
        links = get_links(current_url)

        for link in links:
            full_path = urljoin(current_url, link)
            if full_path.endswith(".php"):
                all_files.add(full_path)
            elif "/" in link and full_path.startswith(FILE_SOURCE_URL):
                to_check.append(full_path)  # Keep crawling deeper

    return list(all_files)

# Run the function
files = get_all_files(FILE_SOURCE_URL)
print("\nFound PHP files:")
print("\n".join(files))
