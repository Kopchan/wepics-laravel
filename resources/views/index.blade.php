<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Wepics</title>
    <meta property="og:site_name" content="{{ config('app.name') }}" />
    @if(isset($album))
      @if(!isset($image))
        <meta property="og:title"         content="{{ $album->name }}" />
        <meta property="og:image:type"    content="image/png" />
        <meta property="og:image:width"   content="1200" />
        <meta property="og:image:height"  content="1200" />
        <meta property="og:image"         content="{{ route('get.album.og', $album->hash) }}" />
        <meta name="twitter:card"         content="summary_large_image">
        <meta name="twitter:image:type"   content="image/png" />
        <meta name="twitter:image:width"  content="1200" />
        <meta name="twitter:image:height" content="1200" />
        <meta name="twitter:image"        content="{{ route('get.album.og', $album->hash) }}" />
      @else
        <meta property="og:title"         content="{{ $image->name }}" />
        <meta property="og:description"   content="Explore more images in {{ $album->name }}" />
        <meta property="og:image:width"   content="{{ $image->widthThumb }}" />
        <meta property="og:image:height"  content="{{ $image->heightThumb }}" />
        <meta property="og:image"         content="{{ $image->urlRoute }}" />
        <meta name="twitter:card"         content="summary_large_image">
        <meta name="twitter:image:width"  content="{{ $image->widthThumb }}" />
        <meta name="twitter:image:height" content="{{ $image->heightThumb }}" />
        <meta name="twitter:image"        content="{{ route('get.image.thumb', [$album->hash, $image->hash, $image->orient, 1080]) }}" />
      @endif
    @else
      @if(Request::is('/'))
        <meta property="og:title" content="Homepage">
      @else
        <meta property="og:title" content="Wepics">
      @endif
      <meta property="og:image" content="/favicon/maskable_icon_x512.png">
    @endif
    <meta property="og:type" content="website" />

    <link rel="manifest" href="/manifest.json">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#fff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000" media="(prefers-color-scheme:  dark)">

    <meta name="application-name"              content="Wepics">
    <meta name="mobile-web-app-capable"        content="yes">
    <meta name="msapplication-navbutton-color" content="#000">
    <meta name="apple-mobile-web-app-capable"          content="yes">
    <meta name="apple-mobile-web-app-title"            content="Wepics">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <link rel="icon"             type="image/png" sizes="512x512" href="/favicon/icon_x512.png">
    <link rel="apple-touch-icon" type="image/png" sizes="512x512" href="/favicon/icon_x512.png">
    <link rel="icon"             type="image/svg+xml"             href="/favicon/icon.svg">
    <script type="module" crossorigin src="/assets/index-WOZRU7no.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-DuhjQV0E.css">
  </head>
  <body>
    <div id="app"></div>

    <svg style="display: none" width="0" height="0">
      <filter id="ambient-light" y="-50%" x="-50%" width="200%" height="200%">
        <feGaussianBlur in="SourceGraphic" stdDeviation="40" result="blurred" />
        <feColorMatrix type="saturate" in="blurred" values="4" />
        <feComposite in="SourceGraphic" operator="over" />
      </filter>
    </svg>
  </body>
</html>
