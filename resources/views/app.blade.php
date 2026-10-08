<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }}</title>
    <meta property="og:site_name" content="{{ config('app.name') }}" />
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
              $tail = ($duration &&  $size)
                    ? "$duration and $size"
                    : ($duration ??  $size);

              $description .= ($hasCounters ? ', totaling ' : ' with totaling '). $tail;
            }
          }
        @endphp
        <meta property="og:title"            content="{{ $album->name }}" />
        <meta property="og:description"      content="{{ $description }}" />
        <meta property="og:image:type"       content="image/png" />
        <meta property="og:image:width"      content="1200" />
        <meta property="og:image:height"     content="1200" />
        <meta property="og:image"            content="{{ route('album.og', $album->hash, false) }}" />
        <meta name="twitter:card"            content="summary_large_image">
        <meta name="twitter:image:type"      content="image/png" />
        <meta name="twitter:image:width"     content="1200" />
        <meta name="twitter:image:height"    content="1200" />
        <meta name="twitter:image"           content="{{ route('album.og', $album->hash, false) }}" />
      @else
        <meta property="og:image:type"       content="image/webp" />
        <meta property="og:title"            content="{{ $image->name }}" />
        <meta property="og:image:width"      content="{{ $image->widthThumb }}" />
        <meta property="og:image:height"     content="{{ $image->heightThumb }}" />
        <meta property="og:image"            content="{{ $image->urlThumbRoute }}" />
        <meta name="twitter:image:type"      content="image/webp" />
        <meta name="twitter:image:width"     content="{{ $image->widthThumb }}" />
        <meta name="twitter:image:height"    content="{{ $image->heightThumb }}" />
        <meta name="twitter:image"           content="{{ $image->urlThumbRoute }}" />
        @if($image->type === 'video' || $image->type === 'imageAnimated')
          <meta property="og:description"    content="Explore {{
            (($album?->videos_count ?? 0) > 1
            ? ($album->videos_count - 1)." more videos in "
            : (($album?->albums_count ?? 0) > 1
              ? "$album->albums_count sub-albums in "
              : ''
            ))
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
          <meta name="twitter:card"          content="summary_large_image">
          <meta property="og:description"    content="Explore {{
            (($album?->images_count ?? 0) > 1
            ? ($album->images_count - 1)." more images in "
            : (($album?->albums_count ?? 0) > 1
              ? "$album->albums_count sub-albums in "
              : ''
            ))
          }}{{ $album->name }}" />
         @endif
      @endif
    @else
      @if(Request::is('/'))
        <meta property="og:title" content="Homepage" />
      @else
        <meta property="og:title" content="Wepics" />
      @endif
      <meta property="og:image" content="/icon/maskable512.png" />
    @endif
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#fff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="darkreader-lock">
    <link rel="icon"             type="image/png" sizes="16x16"   href="/icon/circle16.png">
    <link rel="icon"             type="image/png" sizes="32x32"   href="/icon/circle32.png">
    <link rel="icon"             type="image/png" sizes="512x512" href="/icon/circle512.png">
    <link rel="icon"             type="image/svg+xml"             href="/icon/circle.svg">
    <link rel="apple-touch-icon" type="image/png" sizes="192x192" href="/icon/maskable192.png">
    <script type="module" crossorigin src="/assets/index-DrSVe21N.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-2w_otyBV.css">
    <link rel="manifest" href="/manifest.webmanifest">
    <script id="vite-plugin-pwa:register-sw" src="/registerSW.js"></script>
  </head>
  <body>
    <div id="app"></div>

    <svg style="position: absolute; width: 0; height: 0; visibility: hidden" width="0" height="0">
      <filter id="ambient-light" y="-50%" x="-50%" width="200%" height="200%">
        <feGaussianBlur in="SourceGraphic" stdDeviation="40" result="blurred" />
        <feColorMatrix type="saturate" in="blurred" values="4" />
        <feComposite in="SourceGraphic" operator="over" />
      </filter>
    </svg>
  </body>
</html>
