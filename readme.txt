How to Use the Shopify to WooCommerce Importer Plugin

This guide will walk you through every step of using the Shopify to WooCommerce Importer plugin. Follow these instructions to successfully connect your Shopify store and import your products into WooCommerce.
Step 1: Installation and Activation

First, you need to install the plugin on your WordPress site.

    Prepare the File: Make sure you have the plugin saved as a .zip file (e.g., shopify-importer.zip).

    Navigate to Plugins: In your WordPress admin dashboard, go to Plugins → Add New.

    Upload Plugin: Click the Upload Plugin button at the top of the page.

    Choose and Install: Click Choose File, select your shopify-importer.zip file, and click Install Now.

    Activate: Once the installation is complete, click the Activate Plugin button.

Step 2: One-Time Verification

The first time you open the plugin, you'll be asked to verify your usage. This is a one-time step.

    Go to the Plugin Page: Click on the new "Shopify Importer" menu item in your WordPress admin sidebar.

    Enter Your Details: You will see a simple form asking for your email and website. The fields will be pre-filled with your admin email and site URL, but you can change them if needed.

    Activate: Click the Activate Plugin button. This will send an email notification to the plugin developer and unlock the full functionality for you permanently.

Step 3: Get Your Shopify API Credentials

To allow the plugin to read your products, you need to create a secure API connection from your Shopify store.

    Log in to Shopify: Open your Shopify admin panel.

    Go to Apps: In the bottom-left menu, click Settings, then Apps and sales channels.

    Develop Apps: Click Develop apps. If prompted, click Allow custom app development.

    Create an App: Click the Create an app button.

    Name the App: Give your app a name (e.g., "WooCommerce Importer") and click Create app.

    Configure Scopes:

        Go to the Configuration tab.

        In the "Admin API integration" section, click Configure.

        In the search box, type "products" and check the box for read_products. This is the only permission the plugin needs.

        Click Save.

    Install and Get Keys:

        Go to the API credentials tab.

        Click the Install app button. Confirm the installation.

        The page will now show your credentials. Click Reveal token once.

        IMPORTANT: The Admin API access token is shown only once. Copy it immediately.

        API key → This is your "Shopify API Key" in the plugin.

        Admin API access token → This is your "Shopify API Password" in the plugin.

Step 4: Save Settings in WordPress

Now, go back to the Shopify Importer page in WordPress.

    Enter Credentials: Carefully paste the Shopify Store URL, API Key, and API Password (Admin API access token) into the corresponding fields.

    Save: Click the Save Settings button. The page will reload, and your credentials will be securely saved.

Step 5: Fetch, Filter, and Select Products

Once your settings are saved, the import section will become active.

    Fetch Products: Click the Fetch Available Products button. The plugin will connect to your Shopify store and retrieve all your products.

    View Product List: A table will appear showing your products with their Vendor/Brand and Tags.

    Filter and Paginate:

        Use the search box to instantly filter the list by name.

        Use the Next and Previous buttons to navigate through pages if you have more than 20 products.

    Select Products:

        Check the boxes next to the individual products you want to import.

        Click the checkbox in the header to select all products on the current page.

        Click the "Select/Deselect All Filtered" link to select all products that match your search, even across multiple pages.

Step 6: Run the Import

After selecting your products, you're ready to import.

    Start Import: Click the Import Selected Products button.

    Monitor Progress: A progress bar and a live log will appear. The plugin will import products one by one to avoid server timeouts.

        Green Log Entry: The product was imported successfully. The log will show which categories it was added to.

        Red Log Entry: The product was skipped (usually because it already exists).

    Wait for Completion: The process is finished when the progress bar reaches 100% and the status says "Import complete!".

Step 7: Verify Your Products

    Go to WooCommerce: In your WordPress admin, navigate to Products → All Products.

    Check Products: You will see the newly imported items from Shopify.

    Verify Details: Click on a product to edit it. Check that the categories (from Shopify collections), tags, and the "Brand" attribute (from the Shopify vendor) have been assigned correctly.

Step 8: Supporting the Plugin (Optional)

On the right side of the settings page, you will find a "Credits & Support" box. If you find the plugin helpful, you can support its development using the custom PayPal payment system.
