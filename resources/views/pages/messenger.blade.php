@extends('layouts.user-no-nav')

@section('page_title', __('Messenger'))

@section('styles')
    {!!
        Minify::stylesheet([
            '/libs/@selectize/selectize/dist/css/selectize.css',
            '/libs/@selectize/selectize/dist/css/selectize.bootstrap4.css',
            '/libs/dropzone/dist/dropzone.css',
            '/libs/photoswipe/dist/photoswipe.css',
            '/libs/photoswipe/dist/default-skin/default-skin.css',
            '/css/pages/messenger.css',
            '/css/pages/checkout.css'
         ])->withFullUrl()
    !!}
    <style>
        .conversations-list .pl-3 {
            padding-left: 0 !important;
        }
        @media (max-width: 390px) {
            .d-flex span {
                font-size: 14px; /* Tamanho da fonte ajustado para telas menores */
            }
        }
        @media (max-width: 349px) {
            .d-flex span {
                font-size: 12px; /* Tamanho da fonte ajustado para telas menores */
            }
        }
        @media (max-width: 299px) {
            .d-flex span {
                font-size: 10px; /* Tamanho da fonte ajustado para telas menores */
            }
        }
        @media (max-width: 259px) {
            .d-flex span {
                font-size: 8px; /* Tamanho da fonte ajustado para telas menores */
            }
        }
    </style>
    
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/js/messenger/messenger.js',
            '/js/messenger/elements.js',
            '/libs/@selectize/selectize/dist/js/standalone/selectize.min.js',
            '/libs/dropzone/dist/dropzone.js',
            '/js/FileUpload.js',
            '/js/plugins/media/photoswipe.js',
            '/libs/photoswipe/dist/photoswipe-ui-default.min.js',
            '/js/plugins/media/mediaswipe.js',
            '/js/plugins/media/mediaswipe-loader.js',
            '/libs/@joeattardi/emoji-button/dist/index.js',
            '/js/pages/lists.js',
            '/js/pages/checkout.js',
            '/libs/pusher-js-auth/lib/pusher-auth.js'
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
    @include('elements.uploaded-file-preview-template')
    @include('elements.photoswipe-container')
    @include('elements.report-user-or-post',['reportStatuses' => ListsHelper::getReportTypes()])
    @include('elements.feed.post-delete-dialog')
    @include('elements.feed.post-list-management')
    @include('elements.messenger.message-price-dialog')
    @include('elements.checkout.checkout-box')
    @include('elements.attachments-uploading-dialog')
    @include('elements.messenger.locked-message-no-attachments-dialog')
    <div class="row">
        <div class="min-vh-100 col-12">
            <div class="container messenger min-vh-100">
                <div class="row min-vh-100">
                    <div class="col-3 col-xl-3 col-lg-3 col-md-3 col-sm-3 col-xs-2 border border-right-0 border-left-0 rounded-left conversations-wrapper min-vh-100 overflow-hidden border-top ">
                        <div class="d-flex justify-content-center justify-content-md-between pt-3 pr-1 pb-2">
                            <h5 class="d-none d-md-block text-truncate pl-3 pl-md-0 text-bold {{(Cookie::get('app_theme') == null ? (getSetting('site.default_user_theme') == 'dark' ? '' : 'text-dark-r') : (Cookie::get('app_theme') == 'dark' ? '' : 'text-dark-r'))}}">{{__('Contacts')}}</h5>
                            <span data-toggle="tooltip" title="" class="pointer-cursor"
                                  @if(!count($availableContacts))
                                    data-original-title="{{trans_choice('Before sending a new message, please subscribe to a creator a follow a free profile.',['user' => 0])}}"
                                  @else
                                    data-original-title="{{trans_choice('Send a new message',['user' => 0])}}"
                                  @endif
                            >
                                <a title="" class="pointer-cursor new-conversation-toggle" data-original-title="{{trans_choice('Send a new message',['user' => 0])}}">  <div class="mt-0 h5">@include('elements.icon',['icon'=>'create-outline','variant'=>'medium']) </div> </a>
                            </span>
                        </div>
                        <div class="conversations-list">
                            @if($lastContactID == false)
                                <div class="d-flex mt-3 mt-md-2 pl-3 pl-md-0 mb-3 pl-md-0" style="padding-left: 0 !important;"><span>{{__('Click the text bubble to send a new message.')}}</span></div>
                            @else
                                @include('elements.preloading.messenger-contact-box', ['limit'=>3])
                            @endif
                        </div>
                    </div>
                    <div class="col-9 col-xl-9 col-lg-9 col-md-9 col-sm-9 col-xs-10 border conversation-wrapper rounded-right p-0 d-flex flex-column min-vh-100">
                        @include('elements.message-alert')
                        @include('elements.messenger.messenger-conversation-header')
                        @include('elements.messenger.messenger-new-conversation-header')
                        @include('elements.preloading.messenger-conversation-header-box')
                        @include('elements.preloading.messenger-conversation-box')
                        <div class="conversation-content pt-4 pb-1 px-3 flex-fill">
                        </div>
                        <div class="dropzone-previews dropzone w-100 ppl-0 pr-0 pt-1 pb-1"></div>
                        <div class="conversation-writeup pt-1 pb-1 d-flex align-items-center mb-1 {{!$lastContactID ? 'hidden' : ''}}">
                            <div class="messenger-buttons-wrapper d-flex pl-2">
                                <button class="btn btn-outline-primary btn-rounded-icon messenger-button attach-file mx-2 file-upload-button to-tooltip" data-placement="top" title="{{__('Attach file')}}">
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'document','variant'=>''])
                                    </div>
                                </button>
                            </div>
                            <form class="message-form w-100 d-flex align-items-center">
                                <div class="input-group flex-grow-1 d-flex">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="receiverID" id="receiverID" value="">
                                    <textarea name="message" class="form-control messageBoxInput" placeholder="{{__('Write a message..')}}" onkeyup="messenger.textAreaAdjust(this)"></textarea>
                                </div>
                                <div class="ml-2 d-flex align-items-center justify-content-center">
                                    <span class="h-pill h-pill-primary rounded trigger" data-toggle="tooltip" data-placement="top" title="Like">😊</span>
                                </div>
                            </form>
                            <div class="messenger-buttons-wrapper d-flex">
                                @if((GenericHelper::creatorCanEarnMoney(Auth::user()) && !(!GenericHelper::isUserVerified() && getSetting('site.enforce_user_identity_checks'))) /*|| Auth::user()->role_id === 1*/)
                                    <button class="btn btn-outline-primary btn-rounded-icon messenger-button mx-2 to-tooltip" data-placement="top" title="{{__('Message price')}}" onClick="messenger.showSetPriceDialog()">
                                        <div class="d-flex justify-content-center align-items-center">
                                            <span class="message-price-lock">@include('elements.icon',['icon'=>'lock-open','variant'=>''])</span>
                                            <span class="message-price-close d-none">@include('elements.icon',['icon'=>'lock-closed','variant'=>''])</span>
                                        </div>
                                    </button>
                                @endif
                                <button class="btn btn-outline-primary btn-rounded-icon messenger-button send-message mr-2 to-tooltip" onClick="messenger.sendMessage()" data-placement="top" title="{{__('Send message')}}">
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'paper-plane','variant'=>''])
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('elements.standard-dialog',[
    'dialogName' => 'message-delete-dialog',
    'title' => __('Delete message'),
    'content' => __('Are you sure you want to delete this message?'),
    'actionLabel' => __('Delete'),
    'actionFunction' => 'messenger.deleteMessage();',
])
@stop
