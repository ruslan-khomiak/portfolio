$(document).ready(function () {
    var timerId = false;

    // Disable click on Enter
    $('.search-phrase').on('keyup keypress', function (e) {
        var code = e.keyCode || e.which;
        if (code == 13) {
            e.preventDefault();

            return false;
        }
    });

    $(document).on('input', '.search-phrase', function (e) {
        e.preventDefault();

        if (timerId) {
            clearTimeout(timerId);
        }

        timerId = setTimeout(sendSearchRequest, 1000);
    });

    var sendSearchRequest = function () {
        var $searchForm = $('#search-form');
        var $searchResults = $('#search-results');

        $.post($searchForm.attr('action'), $searchForm.serialize(), function (response) {
            if (response.status == 'success') {
                var template = _.template($('#search-template').html());

                var posts = '';
                if (response.posts) {
                    $searchResults.html('');
                    for (var post in response['posts']) {
                        var item = response['posts'][post];

                        posts += template({
                            title: item['title'],
                            text: item['text'],
                            imagePreview: item['preview_image'],
                            imageAlt: item['title']
                        });
                    }

                    $('#search-results').append(posts);
                }
            }

            timerId = false;
        });

    };
});