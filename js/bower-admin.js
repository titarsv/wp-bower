jQuery(document).ready(function($){
    if($('#post_type').val() == "bower_component") {
        var title = $('#title');
        if(title.val() != ''){
            title.attr('readonly', true);
        }else{
            $('#publish').attr('disabled', true);
            var ready = true;
            var search = '';
            title.keyup(function () {
                search = $(this).val();
                if(!$(this).prop('readonly')) {
                    search_bower_components();
                }
            });

            function search_bower_components() {
                if (search != '' && ready) {
                    ready = false;
                    last_search = search;
                    $('#wpwrap').loader('show');
                    $.post('/wp-admin/admin-ajax.php', {
                        action: 'find_bower_components_action',
                        find: search
                    }, function (response) {
                        ready = true;
                        if (search != last_search)
                            search_bower_components();
                        else {
                            $('#titlediv').find('.inside').html(response);
                            $('#wpwrap').loader('hide');
                        }
                    });
                }
            }

            $(document).on('click', '[data-bower-component]', function (e) {
                e.preventDefault();
                var component = $(this).data('bower-component');
                title.val(component).attr('readonly', true);
                $('[data-bower-component]').attr('disabled', true);
                $('#wpwrap').loader('show');
                $.post('/wp-admin/admin-ajax.php', {
                    action: 'install_bower_component_action',
                    component: component
                }, function (response) {
                    $('#titlediv').find('.inside').html(response);
                    $('#wpwrap').loader('hide');
                    var components = $('.installed-bower-component');
                    if (components.length > 0) {
                        var btn = $('#publish');
                        $('#post_status').replaceWith('<input type="hidden" id="post_status" name="post_status" value="publish">');
                        btn.attr('disabled', false);
                        $('#post').submit();
                    }
                });
            });
        }

        var bower_filter = function(obj){
            if(obj.val() == 'main'){
                $('#bower_components_filter_container').removeClass('min').addClass('main');
            }else if(obj.val() == 'main min'){
                $('#bower_components_filter_container').addClass('main').addClass('min');
            }else{
                $('#bower_components_filter_container').removeClass('main').removeClass('min');
            }
        };

        var filter_select = $('#bower_components_filter');
        bower_filter(filter_select);

        filter_select.change(function(){
            bower_filter($(this));
        });
    }
});