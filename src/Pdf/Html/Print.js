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

/**
 * Before Print Event
 *
 * This is called by phantomjs before printing the HTML document to PDF.
 * Allowing us here to make adjustments to the DOM.
 *
 * IMPORTANT: Please ensure that this method remain syncronous!
 *
 * @param string width The width of the page, includes measurement type.
 * @param string height The height of the page, includes measurement type.
 * @param string margin The margin of the page, includes measurement type.
 */
function beforePrint(width, height, margin)
{
	// Firstly the dimensions provided contain the measurement type at the end.
	// This script strictly assumes we are dealing with mm so lets just strip
	// that away with a simple parseInt call.
	width = parseInt(width);
	height = parseInt(height);
	margin = parseInt(margin);

	// Now calculate the avaliable width and height
	// ie: taking into account the margin
	var realWidth = width - (margin/2);
	var realHeight = height - (margin/2);

	// For some strange reason this half a millimeter makes all the diffrence.
	// If we do not subtract this then we will get an extra blank page for
	// every page that exists.
	realHeight = realHeight - 0.5;

	// Now set all the pages to these new dimensions
	$('.page').css({ width: realWidth+'mm', height: realHeight+'mm' });

	// Now loop through each page and check if it has overflowing content
	splitPages(realWidth, realHeight, false);
}

/**
 * Split Pages when content in the "main" container overflows.
 *
 * @param int realWidth The calculated width of the page.
 * @param int realHeight The calculated height of the page.
 * @param boolean recurse Use recursievly.
 */
function splitPages(realWidth, realHeight, recurse)
{
	$('.page').each(function(pageNo, page)
	{
		if ($(page)[0].scrollHeight > $(page).outerHeight(true))
		{
			// Create a new page to house the overflown content
			var new_page =
			$('\
				<div class="page">\
					<header>'+$(page).find('header').html()+'</header>\
					<main></main>\
					<footer>'+$(page).find('footer').html()+'</footer>\
				</div>\
			');

			// Set the dimensions of the new page
			$(new_page).css({ width: realWidth+'mm', height: realHeight+'mm' });

			// Calculate the maximum bottom position
			// for the current pages main container.
			var allowed_bottom = $(page).find('main').position().top +
			(
				$(page).outerHeight(true) -
				$(page).find('header').outerHeight(true) -
				$(page).find('footer').outerHeight(true)
			);

			// Loop through all the immediate children
			// of the current pages main container.
			$(page).find('main').children().each(function(childIndex, child)
			{
				// Grab the childs top and bottom
				var top = $(child).position().top;
				var bottom = top + $(child).outerHeight(true);

				// If the child does not fit and has overflown then lets move it
				if (top >= allowed_bottom || bottom > allowed_bottom)
				{
					$(child).appendTo($(new_page).find('main'));
				}
			});

			// Insert the new page into the dom
			$(page).after(new_page);

			// We created a new page so after this loop
			// is complete we will need to run ourselves again.
			recurse = true;
		}
	});

	// Call ourselves again if we created new pages above.
	if (recurse === true) splitPages(realWidth, realHeight, recurse);
}


function $var(name)
{
	document.write(name);
}
