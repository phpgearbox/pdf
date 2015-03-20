////////////////////////////////////////////////////////////////////////////////
// __________ __             ________                   __________
// \______   \  |__ ______  /  _____/  ____ _____ ______\______   \ _______  ___
//  |     ___/  |  \\____ \/   \  ____/ __ \\__  \\_  __ \    |  _//  _ \  \/  /
//  |    |   |   Y  \  |_> >    \_\  \  ___/ / __ \|  | \/    |   (  <_> >    <
//  |____|   |___|  /   __/ \______  /\___  >____  /__|  |______  /\____/__/\_ \
//                \/|__|           \/     \/     \/             \/            \/
// -----------------------------------------------------------------------------
//          Designed and Developed by Brad Jones <brad @="bjc.id.au" />
// -----------------------------------------------------------------------------
////////////////////////////////////////////////////////////////////////////////

// Import some modules
var
	webpage = require('webpage'),
	system = require('system')
;

// Assign our global exception handler
phantom.onError = whoops;

// Read in our cli arguments
var args = readArgs
({
	url: null,
	output: null,
	width: '210mm',
	height: '297mm'
});

// Create our page
var page = webpage.create();

// Set the paper size
page.paperSize = { width: args.width, height: args.height };

// Normalise the paper size
var normalisedPaperSize = normalisePageSize(args.width, args.height);

// Open up the page
page.open(args.url, function (status)
{
	if (status !== 'success')
	{
		// Not sure why but throwing an exception here makes phantomjs hang.
		// The exception gets output to stderr but for some odd reason
		// phantom.exit() in whoops appears not to work.
		// throw new Error('Unable to load the url: ' + args.url);

		system.stderr.writeLine('Error: Unable to load the url: ' + args.url);
		return phantom.exit(1);
	}

	// This provides an opportunity for the page to perform dom
	// manipulations and calculations before being printed to a PDF.
	// We pass through the paper size so that it knows how big the window is.
	page.evaluate
	(
		function(width, height, version)
		{
			// NOTE: evaluate is syncronous, so long as beforePrint
			// remains syncronous as well, we should be all good.
			if (beforePrint) beforePrint(width, height, version);
		},
		normalisedPaperSize.width,
		normalisedPaperSize.height,
		phantom.version.major
	);

	// Save the page to a PDF file
	page.render(args.output, {format: 'pdf', quality: '100'});

	// Shutdown
	phantom.exit();
});

////////////////////////////////////////////////////////////////////////////////
// HELPER FUNCTIONS BELOW HERE
////////////////////////////////////////////////////////////////////////////////

/**
 * Normalise and Convert the mm Dimensions of the Page into Pixels
 *
 * There are some major scaling diffrences between phantomjs 1.x and 2.x so
 * we convert to pixels ourselves in an attempt to eliminate issues that arise
 * from this. The results have been optimised to work with phantomjs 2.x
 *
 * @see https://github.com/ariya/phantomjs/issues/12936
 *
 * @param string width The width of the page, includes measurement type.
 * @param string height The height of the page, includes measurement type.
 * @return object The width and height in pixels of the page.
 */
function normalisePageSize(width, height)
{
	// Define the PPI value depending on the version of phantomjs.
	if (phantom.version.major == 2)
	{
		var ppi = 72;
	}
	else
	{
		var ppi = 90;
	}

	// The dimensions provided contain the measurement type at the end.
	// We strictly assume we are dealing with mm. Get with the times America :)
	// So lets just strip that away with a simple parseInt call.
	width = parseInt(width);
	height = parseInt(height);

	// Convert mm to pixels
	var mm_to_inch = 25.4;
	width = (width / mm_to_inch) * ppi;
	height = (height / mm_to_inch) * ppi;

	// Finally return an object with the nromalised sizes.
	return { width: width, height: height };
}

/**
 * Simple Error/Exception Handler
 *
 * @param string msg The error message to output.
 * @param object trace The stack trace
 */
function whoops(msg, trace)
{
	var msgStack = [];

	msgStack.push(msg);
	msgStack.push('');

	if (trace && trace.length)
	{
		msgStack.push('TRACE:');

		trace.forEach(function(t)
		{
			msgStack.push
			(
				' -> ' + (t.file || t.sourceURL) + ': ' + t.line +
				(t.function ? ' (in function ' + t.function +')' : '')
			);
		});
	}

	system.stderr.writeLine(msgStack.join('\n'));

	phantom.exit(1);
};

/**
 * Reads in the CLI Arguments
 *
 * This was inspired by: http://goo.gl/hzK0H5
 *
 * @param object defaults An object of default options.
 *                        Anthing set to null must be provided.
 *                        Anything with a value or set to undefined is optional.
 *
 * @throws Error When we find an unknown argument.
 *
 * @return object
 */
function readArgs(defaults)
{
	// Loop through the supplied arguments
	system.args.forEach(function(arg, index, args)
	{
		// We only care about arguments that start with a "-"
		if (arg[0] != '-') return;

		// Remove any preceding dashes from the argument name
		arg = arg.replace(/-/g, '');

		// Make sure the argument is a valid option
		if (!defaults.hasOwnProperty(arg))
		{
			throw new Error('Unknown argument: ' + arg);
		}

		// Grab the next argument, as this will be the actual value
		// eg: phantomjs script.js --option-name option-value
		// TODO: support the likes of: --option-name=option-value
		var value = args[index+1];

		// Assign the argument value
		defaults[arg] = value;
	});

	// Now make sure we don't have any null values
	Object.keys(defaults).forEach(function(key)
	{
		if (defaults[key] === null)
		{
			throw new Error('Required argument not supplied: ' + key);
		}
	});

	// Finally return our arguments object
	return defaults;
}
