@extends('layouts.user-no-nav')

@section('page_title', __('Bookmarks'))

@section('styles')
    {!!
        Minify::stylesheet([
            '/libs/swiper/swiper-bundle.min.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/bookmarks.css',
            '/css/posts/post.css',
            '/css/pages/checkout.css'
         ])->withFullUrl()
    !!}
    @if(getSetting('feed.post_box_max_height'))
        @include('elements.feed.fixed-height-feed-posts', ['height' => getSetting('feed.post_box_max_height')])
    @endif
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/js/pages/checkout.js',
            '/js/PostsPaginator.js',
            '/js/CommentsPaginator.js',
            '/js/Post.js',
             '/js/pages/lists.js',
            '/js/pages/bookmarks.js',
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
    <div class="">
        <div class="row">

            <div class="col-12 col-md-6 col-lg-3 mb-3 settings-menu pr-0">
                <div class="bookmarks-menu-wrapper">
                    <div class="mt-3 ml-3">
                        <h5 class="text-bold {{(Cookie::get('app_theme') == null ? (getSetting('site.default_user_theme') == 'dark' ? '' : 'text-dark-r') : (Cookie::get('app_theme') == 'dark' ? '' : 'text-dark-r'))}}">{{__('Bookmarks')}}</h5>
                    </div>
                    <hr class="mb-0">
                    <div class="d-lg-block bookmarks-nav">
                        <div class="d-none d-md-block">
                            @include('elements.bookmarks.bookmarks-menu',['variant' => 'desktop'])
                        </div>
                        <div class="bookmarks-menu-mobile d-block d-md-none mt-3">
                            @include('elements.bookmarks.bookmarks-menu',['variant' => 'mobile'])
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-9 mb-5 mb-lg-0 min-vh-100 border-left border-right settings-content pl-md-0 pr-md-0">
                <div class="px-2 px-md-3">
                    @if(isset($filterType))
                        {{$filterType}}
                    @endif
                </div>
                @include('elements.feed.posts-load-more')
                <div class="feed-box mt-0  pt-4 posts-wrapper">
                    @include('elements.feed.posts-wrapper',['posts'=>$posts])
                </div>
                @include('elements.feed.posts-loading-spinner')
            </div>
            @include('elements.checkout.checkout-box')
        </div>
    </div>

    <div class="d-none">
        <ion-icon name="heart"></ion-icon>
        <ion-icon name="heart-outline"></ion-icon>
    </div>

@stop
