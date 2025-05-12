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
      src: url({{ asset('assets/font/RobotoFlex.woff2') }}) format("woff2"),
           url({{ asset('assets/font/RobotoFlex.ttf') }})   format("truetype");
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
      font-weight: 500;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }
    .title .params {
      font-size: 64px;
      color: #aaa;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 48px;
    }
    .title .params .item {
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
        @if ($album->images_count)
          <div class="item">
              <svg data-v-08208cfe="" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" size="96" class="lucide lucide-images-icon"><path d="M18 22H4a2 2 0 0 1-2-2V6"></path><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"></path><circle cx="12" cy="8" r="2"></circle><rect width="16" height="16" x="6" y="2" rx="2"></rect></svg>
              <p>{{ countToHuman($album->images_count) }}</p>
          </div>
        @endif
        @if ($album->size)
            <div class="item">
                <svg data-v-08208cfe="" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" size="96" class="lucide lucide-save-icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <p>{{ bytesToHuman($album->size) }}</p>
            </div>
        @endif
        @if ($album->albums_count)
            <div class="item">
                <svg data-v-08208cfe="" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" size="96" class="lucide lucide-folders-icon"><path d="M20 17a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3.9a2 2 0 0 1-1.69-.9l-.81-1.2a2 2 0 0 0-1.67-.9H8a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2Z"></path><path d="M2 8v11a2 2 0 0 0 2 2h14"></path></svg>
                <p>{{ countToHuman($album->albums_count) }}</p>
            </div>
        @endif
      </div>
    </div>
  </div>
</body>
</html>
