<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }}</title>
    <meta property="og:site_name"            content="{{ config('app.name') }}" />
    @if(isset($album))
      @if(!isset($image))
        @php
          $parts = [];
          if ($album->albums_count) $parts[] = "{$album->albums_count} sub-album". ($album->albums_count > 1 ? 's' : '');
          if ($album->audios_count) $parts[] = "{$album->audios_count} audio"    . ($album->audios_count > 1 ? 's' : '');
          if ($album->videos_count) $parts[] = "{$album->videos_count} video"    . ($album->videos_count > 1 ? 's' : '');
          if ($album->images_count) $parts[] = "{$album->images_count} image"    . ($album->images_count > 1 ? 's' : '');

          $duration = $album->duration ? durationToHuman($album->duration / 1000).' in length' : null;
          $size     = $album->size     ? bytesToHuman   ($album->size)           .' in size'   : null;

          $hasCounters = !!count($parts);
          $hasContent = $hasCounters || $duration || $size;

          if (!$hasContent)
            $description = 'Empty album';
          else {
            $description = 'Explore an album';

            if (count($parts)) {
              $last = array_pop($parts);
              $description .= ' with '.(count($parts)
                ? implode(', ', $parts) .' and '. $last
                : $last
              );
            }

            if ($duration || $size) {
              $totaling = $duration ?: $size;
              $tail = ($size)
                ? $totaling .' and '. $size
                : $totaling;

              $description .= ($hasCounters ? ', totaling ' : ' with totaling '). $tail;
            }
          }
        @endphp
        <meta property="og:title"            content="{{ $album->name }}" />
        <meta property="og:description"      content="{{ $description }}" />
        <meta property="og:image:type"       content="image/png" />
        <meta property="og:image:width"      content="1200" />
        <meta property="og:image:height"     content="1200" />
        <meta property="og:image"            content="{{ route('get.album.og', $album->hash) }}" />
        <meta name="twitter:card"            content="summary_large_image">
        <meta name="twitter:image:type"      content="image/png" />
        <meta name="twitter:image:width"     content="1200" />
        <meta name="twitter:image:height"    content="1200" />
        <meta name="twitter:image"           content="{{ route('get.album.og', $album->hash) }}" />
      @else
        <meta property="og:title"            content="{{ $image->name }}" />
        <meta property="og:image:width"      content="{{ $image->widthThumb }}" />
        <meta property="og:image:height"     content="{{ $image->heightThumb }}" />
        <meta property="og:image"            content="{{ $image->urlThumbRoute }}" />
        <meta name="twitter:image:width"     content="{{ $image->widthThumb }}" />
        <meta name="twitter:image:height"    content="{{ $image->heightThumb }}" />
        <meta name="twitter:image"           content="{{ $image->urlThumbRoute }}" />
        @if($image->type === 'video' || $image->type === 'imageAnimated')
          <meta property="og:description"    content="Explore {{
            $album->videos_count > 1 ? ($album->videos_count - 1)." more videos in " : ''
          }}{{ $album->name }}" />
          <meta property="og:type"           content="video.other" />
          <meta property="og:video:width"    content="{{ $image->width }}" />
          <meta property="og:video:height"   content="{{ $image->height }}" />
          <meta property="og:video:duration" content="{{ (int)($image->duration_ms / 1000) }}" />
          <meta property="og:video"          content="{{ $image->urlOrigRoute }}" />
          <meta name="twitter:card"          content="player" />
          <meta name="twitter:player:width"  content="{{ $image->width }}" />
          <meta name="twitter:player:height" content="{{ $image->height }}" />
          <meta name="twitter:player"        content="{{ $image->urlOrigRoute }}" />
        @else
          <meta property="og:description"    content="Explore {{
            $album->images_count > 1 ? ($album->images_count - 1)." more images in " : ''
          }}{{ $album->name }}" />
          <meta name="twitter:card"          content="summary_large_image">
         @endif
      @endif
    @else
      @if(Request::is('/'))
        <meta property="og:title" content="Homepage" />
      @else
        <meta property="og:title" content="Wepics" />
      @endif
      <meta property="og:image" content="/favicon/maskable_icon_x512.png" />
    @endif

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

    <meta name="darkreader-lock">

    <link rel="icon"             type="image/png" sizes="512x512" href="/favicon/icon_x512.png">
    <link rel="apple-touch-icon" type="image/png" sizes="512x512" href="/favicon/icon_x512.png">
    <link rel="icon"             type="image/svg+xml"             href="/favicon/icon.svg">
    <script type="module" crossorigin src="/assets/index-DjvJRFZP.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-BCQJSBTa.css">
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
