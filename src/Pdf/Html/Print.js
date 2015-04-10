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
 * @param string width The width of the page, in pixels.
 * @param string height The height of the page, in pixels.
 * @param int version The major version of phantonjs.
 */
function beforePrint(width, height, version)
{
	// For the lazy lets ensure at least one page container exists
	ensureBasicPageStructureExists();

	// Now add default headers and footers
	addDefaultHeadersFooters();

	// Set all the existing pages to the page dimensions provided
	$('.page').css({ width: width, height: height });

	// Now loop through each page and check if it has overflowing content
	splitPages(width, height, 0);

	// Create a Table of Contents
	createToc();

	// Now lets set some special span tag values
	setSpecialFieldValues();
}

/**
 * Ensure the Basic Page Structure Exists
 *
 * Because I know people are lazy, myself included, this function
 * will build some missing containers if they are omitted.
 */
function ensureBasicPageStructureExists()
{
	// We must have at least one page container
	if ($('.page').length == 0)
	{
		var page = $('<div class="page"></div>');

		// The page container must then have a main container
		// NOTE: <header> and <footer>'s are completely optional.
		if ($('main').length == 0)
		{
			$(page).append('<main></main>');

			$('body').children().each(function(index, child)
			{
				$(child).appendTo($(page).children('main'));
			});
		}
		else
		{
			$('body').children().each(function(index, child)
			{
				$(child).appendTo(page);
			});
		}

		$('body').append(page);
	}
}

/**
 * Add Default Headers and Footers
 *
 * If a default header and / or footer exists this will clone that to all pages
 * that do not have an explicity defined header or footer. Thus if a page
 * requires a blank header and footer (eg: A Cover Page) then ensure such a page
 * does contain empty header and footer tags.
 */
function addDefaultHeadersFooters()
{
	if ($('header.default').length > 0)
	{
		var header = $('header.default').first();

		$('.page').each(function(index, page)
		{
			if ($(page).children('header').length == 0)
			{
				$(header).clone().insertBefore
				(
					$(page).children('main')
				);
			}
		});
	}

	if ($('footer.default').length > 0)
	{
		var footer = $('footer.default').first();

		$('.page').each(function(index, page)
		{
			if ($(page).children('footer').length == 0)
			{
				$(footer).clone().insertAfter
				(
					$(page).children('main')
				);
			}
		});
	}
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
	// We only need to become recursive if we add a new page.
	// When we add a new page we need to make sure it also do not
	// contain content that overflows.
	var recurseAgain = false;

	// Loop through all our pages that are part of the DOM.
	$('.page').each(function(pageNo, page)
	{
		// Is page longer than it is allowed to be?
		if ($(page)[0].scrollHeight > $(page).outerHeight(true))
		{
			// Create a new page to house the overflown content
			var new_page = $('<div class="page"><main></main></div>');

			// Insert the new page into the dom
			// Through out this function it is important to add new elements to
			// the DOM as soon as possible. This way when making calculations
			// we are taking the newly added elements into account.
			$(page).after(new_page);

			// Set the dimensions of the new page
			$(new_page).css({ width: realWidth, height: realHeight });

			// Copy the current pages header and footer to the new page
			// if the current page actually has a header and footer.
			if ($(page).children('header').length == 1)
			{
				$(page).children('header').clone().insertBefore
				(
					$(new_page).children('main')
				);
			}

			if ($(page).children('footer').length == 1)
			{
				$(page).children('footer').clone().insertAfter
				(
					$(new_page).children('main')
				);
			}

			// Calculate the total maximum height of the page
			var max_height =
			(
				$(page).innerHeight() -
				$(page).children('header').outerHeight(true) -
				$(page).children('footer').outerHeight(true)
			);

			// Grab the top of the main container
			var main_top = $(page).children('main').position().top;

			// Calculate the maximum bottom position
			// for the current pages main container.
			var allowed_bottom = main_top + max_height;

			// Loop through all the immediate children
			// of the current pages main container.
			var bottom_found = false;
			$(page).children('main').children().each(function(childIndex, child)
			{
				// Grab the childs top and bottom
				var top = $(child).position().top;
				var bottom = top + $(child).outerHeight(true);

				// Calculate the maximum height we can take up on the current
				// page, taking into account the childs top position relative
				// to the top of the main container.
				var child_height = max_height - (top - main_top);

				// This child is way too big and cannot phsically fit on one
				// page. Lets hide the content that does not fit and clone the
				// element into the new page but only start showing from where
				// we finished.
				if ($(child).outerHeight(true) > max_height)
				{
					// Calculate the new height of the element we are about to
					// clone, on each iteration we subtract the child_height
					// so the outer div gets smaller and smaller until it fits
					// on a page.
					var new_height = $(child).outerHeight(true) - child_height;

					// If we come across an element that has already been cloned
					// we need to clone it again because it appears to still not
					// fit on the page.
					if ($(child).hasClass('to-big-outer'))
					{
						// Clone the container
						// We don't need to do any wrapping this time
						var clone = $(child).clone();

						// Add the clone to next page
						$(new_page).children('main').append(clone);

						// Decrease the outer height again
						$(clone).css({ height: new_height });

						// Set the new start position of the inner
						var inner = $(clone).find('.to-big-inner');
						var current_top = $(inner).position().top;
						var new_top = current_top - child_height;
						$(clone).find('.to-big-inner').css({ top: new_top });

						// This next bit accounts for left over space.
						var bottom_of_outer = $(clone).position().top + $(clone).outerHeight(true);
						var bottom_of_inner = $(clone).position().top + ($(inner).outerHeight(true) + $(inner).position().top);
						var diff = bottom_of_outer - bottom_of_inner;
						$(clone).css({ height: new_height - diff });

						// Set the height of the current element
						// so it fits on the previous page
						$(child).css({ height: child_height });

						// We are done, continue the loop.
						return true;
					}

					// Wrap the oversized element in some divs
					// This will allow us to show only the section of the
					// oversized element by changing the top postition of the
					// inner container relative to the outer.
					var container =
					$('\
						<div class="to-big-outer">\
							<div class="to-big-inner"></div>\
						</div>\
					');
					$(child).after(container);
					$(child).appendTo($(container).find('.to-big-inner'));

					// Set some css on the outer container
					// Making it so that any overflowing content is hidden
					$(container).css
					({
						overflow: 'hidden',
						position: 'relative',
						height: child_height
					});

					// Clone the container
					var clone = $(container).clone();

					// Reset the height on outer container
					// This is so that if the element is still too big it
					// will get cloned again on the next iteration of splitPages
					$(clone).css({ height: new_height });

					// Set the starting position of inner container such that
					// the oversized element starts being shown where we cut it
					// off on the previous page.
					$(clone).find('.to-big-inner').css
					({
						position: 'absolute',
						top: -child_height
					});

					// Add the clone to the new page
					$(new_page).children('main').append(clone);

					// We are done, continue the loop.
					return true;
				}

				// Its safe to say that once we have found the first child that
				// does not fit on the current page, that all other children
				// will also not fit.
				if (bottom_found)
				{
					$(child).appendTo($(new_page).children('main'));
					return true;
				}

				// If the child does not fit then lets move it
				if (top >= allowed_bottom || bottom > allowed_bottom)
				{
					$(child).appendTo($(new_page).children('main'));
					bottom_found = true;
				}
			});

			// We created a new page so after this loop
			// is complete we will need to run ourselves again.
			recurseAgain = true;
		}
	});

	// Call ourselves again if we created new pages above.
	if (recurseAgain)
	{
		// In some cases I have found that we can get ourselves
		// into a never ending loop, this protects against that.
		if (recurse < 1000)
		{
			splitPages(realWidth, realHeight, recurse + 1);
		}
	}
}

/**
 * Create Table of Contents
 *
 * If the tag `<ul class="toc"></ul>` exists anywhere in the document
 * we will generate the list items and insert them into the ul container.
 */
function createToc()
{
	// Bail out if we don't have a toc
	if ($('ul.toc').length == 0) return;

	// The toc is based on headings contained inside any main container.
	// Heading in headers and footers have zero effect on the TOC.
	$('main').find('h1,h2,h3,h4,h5,h6').each(function(index, heading)
	{
		// Ensure the element is actually visible. When the splitPages function
		// has cloned large elements such as tables that contain headings.
		// We only want to add the headings that can be seen.
		if (!heading.isVisible(heading)) return true;

		// Ignore headings on the actual TOC page.
		// Otherwise we might end up in a never ending loop
		if ($(heading).parents('.page').find('ul.toc').length > 0) return true;

		// Ignore all headings that have the class "toc-ignore"
		if ($(heading).hasClass('toc-ignore')) return true;

		// Grab the level of the heading
		var level = $(heading).prop('tagName').replace('H', '');

		// Grab the page number the heading is on
		var pageNo = $(heading).parents('.page').index() + 1;

		// Add a new entry
		$('ul.toc').append
		('\
			<li class="toc-level-'+level+'">\
				<span class="toc-section-title">'+$(heading).text()+'<span>\
				<span class="toc-page-no">'+pageNo+'</span>\
			</li>\
		');
	});
}

/**
 * Set Special Field Values
 *
 * A special field in the context of this phantonjs PDF generator is a <span>
 * tag with one of the following class names:
 *
 * 	- current-page
 * 	- total-pages
 *
 * An example may look like: <span class="current-page"></span>
 *
 * After we have finished spliting the pages up we will search for these
 * tags and set their text contents with to appropriate value.
 */
function setSpecialFieldValues()
{
	// Total page count is super easy
	$('.total-pages').text($('.page').length);

	// The current page number is just a matter of looping through each page
	$('.page').each(function(pageNo, page)
	{
		$(page).find('.current-page').text(pageNo + 1);
	});
}
