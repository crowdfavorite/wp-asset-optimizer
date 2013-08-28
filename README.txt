# CF Asset Optimizer Readme

## Server Requirements
- PHP 5.2 or later with multibyte string package installed.
- Server must be capable of making http requests to itself.

## Installation
- Install the plugin in the WordPress plugins directory
- Activate the plugin.
- Go to the settings page. Activate any asset optimizers / minifiers desired. Activate one cache manager.
- Ensure that wp-content is writeable by the web user, or that wp-content/cfao-cache exists and is writeable by the web user if you are using the CF File Cache cache manager.
- Request a page. The Asset Optimizer should automatically include all possible local JS/CSS files and minify them using simple whitespace minification. (Assuming CF Asset Optimizers and CF Asset Minifiers were used)
- Verify concatenation files were set up instead of the usual CSS/JS enqueues.
- Go to the configuration pages for the asset optimizers, double-check that the results are as you'd expect.

## FAQ
- Why did I get a whitescreen after I did a git clone of the plugin into my plugins directory?
 - Ensure that you updated all the plugin's submodules, as it does maintain the php minify library as a submodule.
- Why aren't my assets being concatenated?
 - Ensure that the optimizers are enabled and review their settings to determine whether it had an error concatenating assets.
 - Ensure that you have a cache manager activated.
 - If you are using the CF File Cache Manager, ensure that the wp-content/cfao-cache directory exists and is writeable.
- Why aren't my assets being minified?
 - Ensure that you have a minifier active for that asset type.
 - Ensure that your assets are set to be minified (if you have the option to "Preserve" the asset, then it is minified.)
 - Ensure that you meet any requirements for the minifier you are using. Check your error log for further details.
- What if I want to use a different optimizer, minifier, or cache manager than those that come with your plugin?
 - The plugin includes an interface to register your own modules. Either use modules someone else has made or write your own, register them with the plugin, and then activate them on your site as you please.
- I'm using the CF WP Cache integration plugin. Why are my concatenated files returning 404?
 - You may need to hit your Permalinks page to cause WordPress to flush and regenerate its rewrite rules.
 - Ensure that you have no custom .htaccess rules blocking the URLs given.
 - Ensure you're using a persistent caching plugin of some variety. If you are not, then the cache is lost at the end of the original page request, and cannot be served via an additional request.