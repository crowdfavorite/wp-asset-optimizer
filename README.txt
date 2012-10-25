# CF Asset Optimizer Readme

## Server Requirements
- PHP 5.3 or later with multibyte string package installed.
- Server must be capable of making http requests to itself.
- Server must be capable of making calls to the Google Closure Compiler if JS minification is selected at any level.

## Installation
- Install the plugin in the WordPress plugins directory
- Create a cfao-cache directory in wp-content. **This directory must be writable by the web user.**
- Ensure that the web server is capable of sending a request to itself and getting a successful response.

## Configuration
- Navigate to the homepage. This will cause the plugin to get a list of all styles/scripts used on this page.
- Navigate to other pages as well if they are expected to use scripts that would not be included on the home page.
- Go into the website admin section, and go to the CF Asset Optimizer settings page.
- *The steps below are the only steps guaranteed to work in all cases. They are not the ideal steps, and each site must be tuned manually for best performance.*
	- Check the caching solution checkbox if the site is using a caching solution.
	- Set the JavaScript concatenation level to Whitespace Only.
	- Save the settings.
	- Go to the JavaScript section.
	- Enable all of the scripts hosted on this domain (not scripts hosted on google, wordpress.com, etc)
	- Save the settings.
	- Go to the CSS section
	- Enable all of the CSS files hosted on this domain.
	- Save the settings.
	- Go to the homepage for the site.
		- If the site is not using a caching solution:
			- After the page loads, refresh. The concatenation is handled asynchronously in these cases, so it won't have been done for this request.
		- View the page source. In the header, the scripts and styles should be concatenated. If the concatenation does not match what is expected, review settings.
			- You can tell if they're contactenated if they've become requests to something similar to the following:

			`/wp-content/cfao-cache/[domain_here]/css/0f3bb05f176c9605990f8004949108f2.css?ver=1351111600`

## Maintenance
- If a script or stylesheet is added or changed, it will not be included in the concatenated files served.
	- **Note: Scripts dependent on a script that is not included will also not be included to prevent circular dependency failures.**
- If a script or stylesheet is not included in the concatenated file, and needs to be:
	- Go into the admin settings for the Asset Optimizer
	- Enable the desired scripts and stylesheets that are currently disabled and save those settings.
	- Clear the CSS and JS caches to force the server to rebuild the concatenated files.
	- Request the homepage.
		- Refresh once if you're not using a caching solution, to ensure that you can review the concatenation as completed.
		- Review the page source, ensure that the scripts and stylesheets are concatenated as expected.
- **Note: Concatenated static filenames do not change unless the enqueued script/stylesheet handle (i.e., not the content of those scripts) for one of the concatenated files changes, or a file is added.**