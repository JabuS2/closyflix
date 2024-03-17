@extends('layouts.user-no-nav')
@section('page_title', __('Your feed'))

{{-- Page specific CSS --}}
@section('styles')
    {!!
        Minify::stylesheet([
            '/libs/swiper/swiper-bundle.min.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/css/pages/checkout.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/feed.css',
            '/css/posts/post.css',
            '/css/pages/search.css',
         ])->withFullUrl()
    !!}
    @if(getSetting('feed.post_box_max_height'))
        @include('elements.feed.fixed-height-feed-posts', ['height' => getSetting('feed.post_box_max_height')])
    @endif
@stop

{{-- Page specific JS --}}
@section('scripts')
    {!!
        Minify::javascript([
            '/js/PostsPaginator.js',
            '/js/CommentsPaginator.js',
            '/js/Post.js',
            '/js/SuggestionsSlider.js',
            '/js/pages/lists.js',
            '/js/pages/feed.js',
            '/js/pages/checkout.js',
            '/libs/swiper/swiper-bundle.min.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/libs/@joeattardi/emoji-button/dist/index.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
         ])->withFullUrl()
    !!}

<script>
    // Verifica se os dados de pagamento da Suitpay estão definidos na sessão e exibe o modal do código QR da Suitpay
    @if (Session::has('suitpay_payment_data') && Session::get('suitpay_payment_data')['user_id'] == Auth::user()->id)
        $(document).ready(function() {
            // Função para fechar o modal e recarregar a página
            function closeAndReload() {
                $('#suitpayQrcodeModal').modal('hide');
                location.reload(true); // Use 'true' para recarregar a página do servidor
            }

            // Verifica se o modal já foi exibido anteriormente
            if (localStorage.getItem('suitpayModalDisplayed') !== 'true') {
                $('#suitpayQrcodeModal').modal('show');

                // Adiciona um ouvinte de eventos para recarregar a página quando o modal for fechado manualmente
                $('#suitpayQrcodeModal').on('hidden.bs.modal', function (e) {
                    // Destrói a sessão e fecha o modal manualmente
                    $.ajax({
                        url: '/suitpay/destroy-session',
                        type: 'POST',
                        data: {
                            _token: '{{csrf_token()}}'
                        },
                        success: function(response) {
                            console.log(response);
                            closeAndReload();
                        },
                        error: function() {
                            closeAndReload();
                        }
                    });
                });

                // Oculta o modal após 30 segundos e destrói a sessão
                setTimeout(function() {
                    $.ajax({
                        url: '/suitpay/destroy-session',
                        type: 'POST',
                        data: {
                            _token: '{{csrf_token()}}'
                        },
                        success: function(response) {
                            console.log(response);
                            // Verifica se o pagamento foi bem-sucedido
                            if (response.status === 'PAID_OUT') {
                                // Define o indicador de que o modal foi exibido para evitar mostrá-lo novamente
                                localStorage.setItem('suitpayModalDisplayed', 'true');
                            }
                            // Fecha o modal após a verificação do pagamento, mesmo que não seja bem-sucedido
                            closeAndReload();
                        },
                        error: function() {
                            // Em caso de erro na solicitação AJAX, também fecha o modal e recarrega a página
                            closeAndReload();
                        }
                    });
                }, 30000);
            }
        });
    @endif
</script>

@stop

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-12 col-sm-12 col-lg-8 col-md-7 second p-0 ">
                <div class="d-flex d-md-none px-3 py-3 feed-mobile-search neutral-bg fixed-top-m">
                    @include('elements.search-box')
                </div>

                @if(!getSetting('feed.hide_suggestions_slider'))
                    <div class="d-block d-md-none d-lg-none m-pt-70 feed-suggestions-wrapper">
                        @include('elements.feed.suggestions-box',['profiles'=>$suggestions, 'isMobile'=> true])
                    </div>
                @endif

                {{-- @include('elements.user-stories-box')--}}

                <div class="">
                    @include('elements.message-alert',['classes'=>'pt-4 pb-4 px-2'])
                    @include('elements.feed.posts-load-more')
                    <div class="feed-box mt-0 pt-4 posts-wrapper">
                        @include('elements.feed.posts-wrapper',['posts'=>$posts])
                    </div>
                    @include('elements.feed.posts-loading-spinner')
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-5 col-lg-4 first border-left order-0 pt-4 pb-5 min-vh-100 suggestions-wrapper d-none d-md-block">

                <div class="feed-widgets">
                    <div class="mb-3">
                        @include('elements.search-box')
                    </div>

                    @if(!getSetting('feed.hide_suggestions_slider'))
                        @include('elements.feed.suggestions-box',['profiles'=>$suggestions, 'isMobile'=> false])
                    @endif
                    @if(getSetting('custom-code-ads.sidebar_ad_spot'))
                        <div class="mt-3">
                            {!! getSetting('custom-code-ads.sidebar_ad_spot') !!}
                        </div>
                    @endif

                    @include('template.footer-feed')

                </div>

            </div>
        </div>
        @include('elements.checkout.checkout-box')
    </div>

    <div class="d-none">
        <ion-icon name="heart"></ion-icon>
        <ion-icon name="heart-outline"></ion-icon>
    </div>

    @include('elements.standard-dialog',[
        'dialogName' => 'comment-delete-dialog',
        'title' => __('Delete comment'),
        'content' => __('Are you sure you want to delete this comment?'),
        'actionLabel' => __('Delete'),
        'actionFunction' => 'Post.deleteComment();',
    ])

@stop
