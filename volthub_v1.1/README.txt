VoltHub (no database version)

1. Copy this folder to C:\xampp\htdocs\volthub (or any PHP 8.1+ web root).
2. Open http://localhost/volthub/ - no MySQL needed, only Apache/PHP.
3. On first run the app creates a data/ folder (JSON files) with demo products,
   stock requests and the admin account:  admin / admin123
4. "Reset demo data" on the dashboard restores products and requests.
   Delete the data/ folder to start completely fresh.

The PHP process needs write permission on this folder (data/ and uploads/).
