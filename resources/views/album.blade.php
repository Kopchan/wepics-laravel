<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>{{ $album->name }}</title>
  <style>
    @font-face {
      font-family: "Roboto Flex";
      font-weight: 100 1000;
      src: url({{ asset('assets/RobotoFlex-DLGGeIPC.woff2') }}) format("woff2"),
           url({{ asset('assets/RobotoFlex-BM2Zixa-.ttf') }})   format("truetype");
    }

    html {
      background: #000;
      color: #fff;
      font-family: 'Roboto Flex', 'Roboto', sans-serif;
    }
    * {
      margin: 0;
      padding: 0;
    }
    body {
      margin: 8px;
      overflow: hidden;
    }
    .wall {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      --size: {{ 300 * (1 / ($album->avgRatio ?: 1)) }};
    }
    .wall::after {
      content: '';
      flex-grow: 1e4;
    }
    .img {
      position: relative;
      width:     calc(var(--ratio) * var(--size) * 1px);
      flex-grow: calc(var(--ratio) * var(--size));
    }
    .img i {
      display: block;
      padding-bottom: calc(1 / var(--ratio) * 100%)
    }
    .img img {
      position: absolute;
      top: 0;
      width: 100%;
      vertical-align: bottom;
      border-radius: 12px;
    }
    .center {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .title {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      backdrop-filter: blur(12px);
      background: #2229;
      box-shadow: #000 0 8px 32px;
      border-radius: 24px;
      padding: 16px 32px;
      overflow: hidden;
      max-width: 90%;
    }
    .title .name {
      max-width: 100%;
      font-size: 76px;
      font-weight: 600;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }
    .title .params {
      font-size: 64px;
      color: #aaa;
      display: inline-flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      gap: 12px 48px;
    }
    .title .params .item {
      font-weight: 500;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 16px;
    }
  </style>
</head>
<body>
  <div class="wall">
    @foreach($album->images as $img)
      <div class="img" style="{{ '--ratio:'. $img->ratio }}">
        <i></i>
        <img src="{{ route('get.image.thumb', [$album->hash, $img->hash, 'h', 720]) }}" alt="">
      </div>
    @endforeach
  </div>
  <div class="center">
    <div class="title">
      <h1 class="name">{{ $album->name }}</h1>
      <div class="params">
        @if ($album->audios_count && $album->audios_count > ($album?->medias_count ?? 0) * 0.25)
          <div class="item">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-music2-icon lucide-music-2"><circle cx="8" cy="18" r="4"/><path d="M12 18V2l7 4"/></svg>
            <p>{{ countToHuman($album->audios_count) }}</p>
          </div>
        @endif
        @if ($album->videos_count && $album->videos_count > ($album?->medias_count ?? 0) * 0.25)
          <div class="item">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-video-icon lucide-video"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
            <p>{{ countToHuman($album->videos_count) }}</p>
          </div>
        @endif
        @if ($album->images_count && $album->images_count > ($album?->medias_count ?? 0) * 0.25)
          <div class="item">
              <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image-icon lucide-image"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
              <p>{{ countToHuman($album->images_count) }}</p>
          </div>
        @endif
        @if ($album->albums_count)
          <div class="item">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folders-icon"><path d="M20 17a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3.9a2 2 0 0 1-1.69-.9l-.81-1.2a2 2 0 0 0-1.67-.9H8a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2Z"></path><path d="M2 8v11a2 2 0 0 0 2 2h14"></path></svg>
            <p>{{ countToHuman($album->albums_count) }}</p>
          </div>
        @endif
        @if ($album?->duration && $album->duration > 3_600_000)
          <div class="item">
              <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hourglass-icon lucide-hourglass"><path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg>
              <p>{{ durationToHuman($album->duration / 1000) }}</p>
          </div>
        @endif
        @if ($album->size)
          <div class="item">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save-icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <p>{{ bytesToHuman($album->size) }}</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</body>
</html>
