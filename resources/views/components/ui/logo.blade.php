{{-- Logo principal da MRS (public/images, 269x226). Com `escuro` usa a versão sem fundo nem sombra, feita para ficar direto sobre o azul-marinho do menu e do login. --}}
@props(['altura' => 'h-10', 'escuro' => false])

<img src="{{ asset($escuro ? 'images/logo-mrs-escuro.webp' : 'images/logo-mrs.webp') }}" alt="MRS Logística" width="269" height="226" {{ $attributes->class([$altura, 'w-auto', 'shrink-0']) }}>