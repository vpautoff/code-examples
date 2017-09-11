var DropoffLocator = function() {
    var geocoder = null;
    var infoWindow = null;
    var map = null;
    var searchRequest = null;
    var searchService = null;

    // Search radius, in km.
    var radius = 5;
    var markers = [];

    var init = function() {
        geocoder = new google.maps.Geocoder();
        infoWindow = new google.maps.InfoWindow();
        map = new google.maps.Map($("#map")[0]);
        searchService = new google.maps.places.PlacesService(map);
    };

    // Creates a map marker with the provided place details.
    var placeDetailsHandler = function(place, status) {
        if (status != google.maps.places.PlacesServiceStatus.OK) {
            // No need to show an error here. It just means one less marker.
            return;
        }

        var contents = '<strong>Name: </strong>' + place.name +
            '<br/><strong>Address: </strong>' + place.formatted_address +
            (typeof place.formatted_phone_number !== 'undefined' ? '<br/><strong>Phone: </strong>' + place.formatted_phone_number : '') +
            (typeof place.website !== 'undefined' ? '<br/><strong>Website: </strong><a href="' + place.website + '">' + place.website + '</a>' : '');

        var marker = new google.maps.Marker({
            position: place.geometry.location,
            map: map,
            contents: contents
        });

        google.maps.event.addListener(marker, 'click', function() {
            infoWindow.close();
            infoWindow.setContent(marker.contents);
            infoWindow.open(map, marker);
        });

        markers.push(marker);
    };

    // Displays the search results on the map.
    var searchResultsHandler = function(results, status) {
        if (status != google.maps.places.PlacesServiceStatus.OK) {
            showError('Could not find ' + $('#carrier').val() + ' Drop-off locations within ' + radius + ' km.');
            return;
        }

        // Clear previous search results.
        for (var i in markers) {
            markers[i].setMap(null);
        }
        markers = [];

        // There is no clear method for LatLngBounds object, so we have to create a new object every time.
        var bounds = new google.maps.LatLngBounds();

        // Get the details for each drop-off location and create a marker for it.
        for (var j in results) {
            var detailsRequest = {
                reference: results[j].reference
            };
            searchService.getDetails(detailsRequest, placeDetailsHandler);
            bounds.extend(results[j].geometry.location);
        }

        map.setCenter(bounds.getCenter());
        map.fitBounds(bounds);
    };

    var showError = function(message) {
        if ((searchRequest) && (searchRequest.location)) {
            map.setCenter(searchRequest.location);
            map.setZoom(13);
        }
        $('#dropoff-locator-container .error-message').html(message).css('display', 'inline-block');
    };

    return {
        search: function() {
            $('#dropoff-locator-container .error-message').hide();

            // Convert human-readable addresses into coordinates of latitude and longitude, then search around that location.
            geocoder.geocode({'address': $("#location").val()}, function(results, status) {
                if (status != google.maps.GeocoderStatus.OK) {
                    showError('Could not find ' + $('#carrier').val() + ' Drop-off locations within ' + radius + ' km.');
                    return;
                }

                searchRequest = {
                    location: results[0].geometry.location,
                    radius: radius * 1000,
                    name: $('#dropoff_search_term').val()
                };

                // Search nearby drop-off locations.
                searchService.nearbySearch(searchRequest, searchResultsHandler);
            });
        },

        show: function() {
            var options = {
                modal: true,
                width: 525,
                minWidth: 525,
                dialogClass: 'dropoff-locator',
                title: 'Select a ' + $('#carrier').val() + ' Drop-off Location Near You',
                buttons: {
                    'Close': function() {
                        $(this).dialog("close");
                    }
                },
                open: function(event, ui) {
                    // Putting this code in the create event breaks the initial search for some reason, so we keep it in the open event.
                    init();
                },
                resizeStop: function(event, ui) {
                    map.checkResize();
                }
            };
            $('#dropoff-locator-container').dialog(options);
        }
    }
};

$(function() {
    if (!$('#packing-slip-page').length) {
        return;
    }

    var dropoffLocator = new DropoffLocator();

    $("body#packing-slip-page").unload(function() {
        GUnload();
    });

    $("#packing-slip-page .print").click(function() {
        print();
        return false;
    });

    $("#packing-slip-page .locate_dropoff").click(function() {
        dropoffLocator.show();
        dropoffLocator.search();
        return false;
    });

    $("#packing-slip-page #dropoff-locator-container form").submit(function() {
        dropoffLocator.search();
        return false;
    });
});
