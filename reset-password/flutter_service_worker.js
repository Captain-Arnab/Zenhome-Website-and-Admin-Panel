'use strict';
const MANIFEST = 'flutter-app-manifest';
const TEMP = 'flutter-temp-cache';
const CACHE_NAME = 'flutter-app-cache';

const RESOURCES = {"assets/AssetManifest.bin": "a52b124dfa785c6eb16577e4b24bf822",
"assets/AssetManifest.bin.json": "c1a8bbb486aa7355f5974ef007b30782",
"assets/AssetManifest.json": "1637e3b199ef77330501dcd131748347",
"assets/assets/admin_bg.jpg": "f77155afa1eddd884616e1d2660f6862",
"assets/assets/background.png": "012bed16fe3f84c9f07ceadf73dbf0a1",
"assets/assets/bag_1.png": "b28f3fddf670f9f33dc0fbe685439516",
"assets/assets/bag_10.png": "bd65b1d92b1f02b297748a82ac31dfec",
"assets/assets/bag_2.png": "a9d1f4df6a8ccd9c473b4b3b6059b95d",
"assets/assets/bag_3.png": "aea5b87c1fcd220a5d3d2d2627803e62",
"assets/assets/bag_4.png": "a0b2ac4057d489391e71fc176ef6b1a5",
"assets/assets/bag_5.png": "c2313ed18420a12bf160e7002d407a9f",
"assets/assets/bag_6.png": "c87d93c60e3f457b76bc80851089297b",
"assets/assets/bag_7.png": "e3aef3b140af1abe120adac515c4444e",
"assets/assets/bag_8.png": "3f1577b8b5a545071a24d87fb6c332aa",
"assets/assets/bag_9.png": "14c188978b4c6145ca3f92f75b4e27b8",
"assets/assets/bell-ring.svg": "376323c1852c66bfe710876f55f3738b",
"assets/assets/bottom_yellow.png": "cb862b7bfac0db50b9ef6cc0781b2606",
"assets/assets/box.png": "b142fe275cbdf1523025ceaba4355100",
"assets/assets/calendar-clock.svg": "29e42a21476cf654d67c2a02697466ba",
"assets/assets/cap_1.png": "78df6acb8771793fa4d5e2a4e3e10f66",
"assets/assets/cap_10.png": "109ebe91831f7fc5832e90c151b12f8d",
"assets/assets/cap_2.png": "62003c6ed05757e4a896289f9add3574",
"assets/assets/cap_3.png": "9e29119bde7186ba69278f53929e125b",
"assets/assets/cap_4.png": "7334f905bd67d389d9bc9e3cb5f07f8b",
"assets/assets/cap_5.png": "4b2e9dbf66c194c9937d5d79f083aa11",
"assets/assets/cap_6.png": "0d2e43042d7d9ae9b8b9d9eeac6de6eb",
"assets/assets/cap_7.png": "6e27350e269622b4c274fb48c446346e",
"assets/assets/cap_8.png": "7f98e7727ae2e6e7b9d2ec24df845968",
"assets/assets/cap_9.png": "ccbf1cedf3720d45dec6e52b361aeb5d",
"assets/assets/carpentry.png": "1dad9005f4d776179649d5985e934e03",
"assets/assets/deep_water_services.png": "a138e4d7a849d1aba5346b7a73fbdc89",
"assets/assets/expense.svg": "8215648c6d44d317e3f46941aca70fe5",
"assets/assets/firstScreen.png": "c2527bdadd7e36342c066e2ceaf08bd5",
"assets/assets/fonts/Nunito-Regular.ttf": "b83ce9c59c73ade26bb7871143fd76bb",
"assets/assets/general_checkup.png": "3330cc7447320ba5bf206b268ceff941",
"assets/assets/Group%2520444.png": "8ed5bfe37741053d9b6b47f5f082140f",
"assets/assets/Hamburger-menu.png": "ccd3246c20c636b83aaf01df2c51899d",
"assets/assets/headphones.png": "d1dbe67e4015613c8ce7c9783f78dc04",
"assets/assets/headphones_2.png": "35f4033166ac9bf9e482d9cf544ce99d",
"assets/assets/headphones_3.png": "79330d1cb373f70f58af098ad01136c2",
"assets/assets/headphones_4.png": "29ab512355e0b92deb505326372f8cae",
"assets/assets/headphone_10.png": "aa75ae52cf4967a6701a08b75f93015f",
"assets/assets/headphone_11.png": "856db608b77bc0f117dc89a2ab56bd0c",
"assets/assets/headphone_12.png": "3922dab5fe3f05bad639ed753121a0ab",
"assets/assets/headphone_13.png": "e218b551e2132a8bb039e7faf1df3c2e",
"assets/assets/headphone_14.png": "b9a1d40587f4cf9b57aaa65aac646a79",
"assets/assets/headphone_5.png": "1439c1d8aca4838b1fe8b9f33b3a415a",
"assets/assets/headphone_6.png": "273bab15652f96f97a9136279cebab34",
"assets/assets/headphone_7.png": "c4ec6822047ebe3e7b8a3e66ebd6d97e",
"assets/assets/headphone_8.png": "40ba518d2bcbc27147a5c5fc2934eee8",
"assets/assets/headphone_9.png": "07c6098f024742100a97d054c16deff3",
"assets/assets/holding-hand-delivery.svg": "e2c70dee164045d3d6ce41aa002662b6",
"assets/assets/home.png": "2a413e713384308744856dfc17186b41",
"assets/assets/house_cleaning.jpg": "9f00b66074caa417f401f8bcc61422fb",
"assets/assets/house_cleaning.png": "a8d5069691342507a669fd5d40a576e0",
"assets/assets/icons/10%2520usd.png": "e75b11052957117a546561ac2db467bc",
"assets/assets/icons/5%2520usd.png": "cd12f5eb7b31d1584f028d9a44efac59",
"assets/assets/icons/about_us.png": "cf6b7b77b9f65829f0842e33781f5cf2",
"assets/assets/icons/address_home.png": "2407083aaa4c91aff12331524944f245",
"assets/assets/icons/address_work.png": "a9ce2d6215a9b06b74c7c8341f732de3",
"assets/assets/icons/alerts.svg": "43f53032fd71fe4b4e9c6200c17359b3",
"assets/assets/icons/card.png": "930f092ed158842899c09064e06d7205",
"assets/assets/icons/cart_icon.svg": "42028bd8d48d457ee922cc161f4fe973",
"assets/assets/icons/category_icon.png": "27c80e559a1d656dba8d2cb5d7c52ae1",
"assets/assets/icons/category_icon.svg": "5650cf3f22a04daf93a0b3dfef21cc15",
"assets/assets/icons/change_pass.png": "cb232504461538343ea3eb49e4a57983",
"assets/assets/icons/comment.png": "5b51faaa28262b7fa7131fe05353adb3",
"assets/assets/icons/contact_us.png": "a2f52507c25b657de308b99b3fe224b4",
"assets/assets/icons/country.png": "617a6499a5c575aa48fcc51f0d00f3b1",
"assets/assets/icons/cut_qr.png": "25df7625505659dc0fd7c839ac28baf3",
"assets/assets/icons/denied_wallet.png": "c58b107648ebdf9d392d120d3a891bdc",
"assets/assets/icons/faq.png": "8f14ac68759b1854a8ee955d911ceaa5",
"assets/assets/icons/home.gif": "176d9416cb4b2d3bfe9fd4b4330e105b",
"assets/assets/icons/home.png": "3c31ff080f6170f11e74526628a8d304",
"assets/assets/icons/home_icon.svg": "8227cb7f81bbcd39eede11224285371f",
"assets/assets/icons/language.png": "d5d834765a7d32fd0ca7ab18881938fe",
"assets/assets/icons/legal.png": "bb0e4b908a8467eb92550a1dc7386f9a",
"assets/assets/icons/list.gif": "f05b1a15ec7d71870391bcbd0466d92d",
"assets/assets/icons/list.png": "27c80e559a1d656dba8d2cb5d7c52ae1",
"assets/assets/icons/Logos.png": "fdf061a4bb2c9e04c2d7d2f270abecc2",
"assets/assets/icons/logout.svg": "48625263163902d443cc01e2e4655a06",
"assets/assets/icons/logout_icon.png": "e7e28d6337fed55f53797c1150aaa3df",
"assets/assets/icons/mechanic_icon.png": "f7966e8b29d4e1a5530f6d6e300d6bed",
"assets/assets/icons/notifications.png": "52f2ec1dc6661847645122905d0c8adb",
"assets/assets/icons/notification_icon.png": "4ca75f8071f6cf36f80ce8524b41b165",
"assets/assets/icons/package.png": "abfe9ad8bf2d3e71392aa52ea7613b56",
"assets/assets/icons/payment.svg": "c8934b2ba05da6d44faf6bd5e12fcdab",
"assets/assets/icons/payment_icon.png": "52514e1f731a45fb9d972efb2a94999a",
"assets/assets/icons/profile.gif": "9d7db1c6a643f3f85bf599d546a54a23",
"assets/assets/icons/profile.png": "9958a298e3d30a300cf66aaadbe106d0",
"assets/assets/icons/profile_icon.png": "9958a298e3d30a300cf66aaadbe106d0",
"assets/assets/icons/profile_icon.svg": "0e801108b55a0b18cae251d7772ebc9e",
"assets/assets/icons/QR_code.png": "3cf880650edd50068db82f07f79de161",
"assets/assets/icons/reload_icon.png": "413b6fc7d36d276f71e13dec4ce0a6d7",
"assets/assets/icons/reload_icon.svg": "5940825e6eb0d0d9cc5e5edff87fdb0b",
"assets/assets/icons/schedule.gif": "070fb70162947dbf7e388beb6ef366a7",
"assets/assets/icons/schedule.svg": "b65b84b91bfb282b9ff58b5c8f928c14",
"assets/assets/icons/search_icon.svg": "3e04138b95c4b3778f230950bbd44d56",
"assets/assets/icons/service_icon.png": "c691f14b6c82ec4c462ac656e6ae2d70",
"assets/assets/icons/service_icon.svg": "1ec8c5937b3d8f7395239ab8f202dacf",
"assets/assets/icons/service_tracking.svg": "389820e7c5da2932a87d0dd68af07833",
"assets/assets/icons/service_tracking_icon.png": "072c93a780c9ddbaffe1a7fc777297d8",
"assets/assets/icons/settings.png": "c57d55d53a8345bf244c7dd3acbfa83d",
"assets/assets/icons/settings_icon.png": "da4b691736138c6da13f19e9060c1143",
"assets/assets/icons/settings_icon.svg": "c8aed0cab828aa0426d6f757f36f2c32",
"assets/assets/icons/shopeLogo.png": "4377d43f275620441494efcc4c89ea4d",
"assets/assets/icons/sign_out.png": "3790a5ec87ab1d91a1aeeac24471a737",
"assets/assets/icons/support.png": "3c4dd1f11cbe87953111243867b42ffb",
"assets/assets/icons/technician.svg": "4b36eef88be44499f115aa152f01bf99",
"assets/assets/icons/timer.png": "413b6fc7d36d276f71e13dec4ce0a6d7",
"assets/assets/icons/truck.png": "0ec8def3dea85020c840cd13cc45bbb2",
"assets/assets/icons/user.gif": "e607d024b54139fef503f57e5b0f7eed",
"assets/assets/icons/users.svg": "1c0183dab683c75e1943c9c6842239da",
"assets/assets/icons/user_icon.png": "1808c28bc0329623c43ceab691c9bf7c",
"assets/assets/icons/wallet.png": "866f4e509e9a95fcb117c1353fc4f408",
"assets/assets/interior_services.png": "3d7f72be71d4757f7a153e5080b8a4f4",
"assets/assets/jeans_1.png": "7cc41af2feeafd22641cdaeec2d438d8",
"assets/assets/jeans_10.png": "0ca28e147ee446c3af846e5974e0b657",
"assets/assets/jeans_2.png": "9ecf1028450b056de434b4b89ec2c74c",
"assets/assets/jeans_3.png": "395ad3acd5e8df4c05edcb81b2633a59",
"assets/assets/jeans_4.png": "0449edb8a8d763c313dd9a4db17df953",
"assets/assets/jeans_5.png": "11fb0ea51cf6667bc10c6378418aecfd",
"assets/assets/jeans_6.png": "6a82295b3ee242d1e8941c14d157f54e",
"assets/assets/jeans_7.png": "abd5cea07df25dc783b105bd5b7c13e0",
"assets/assets/jeans_8.png": "1bc82227b81e8efe8df80450655342b2",
"assets/assets/jeans_9.png": "460353c14ccdcc674818db4f48fea2df",
"assets/assets/list.png": "14e4e161f98645ac34a114399212b30a",
"assets/assets/loading.gif": "fed6acbcd9595b6d9b6332c100304357",
"assets/assets/location-alt.svg": "62300091554391316a5af669e367799a",
"assets/assets/logo.png": "8f3b0c0cabc8832e024cd96d91d7ca95",
"assets/assets/panel_flooring.png": "ad23ed0098b7d8555a98f7d033563f94",
"assets/assets/pcb_services.png": "bd0ccc2e08d15e3082ec44534cb6570a",
"assets/assets/profile.png": "7f72d87871fb93d519f4675835abe734",
"assets/assets/red_clear.png": "dc1c8b845ae1b7b498d1c8f0d58ecefd",
"assets/assets/refrigerator.png": "91ae928b062419f6fee4e348cfe9a220",
"assets/assets/register_bg.png": "04a8a46e7935b6d36148c5a9b487d454",
"assets/assets/ring_1.png": "d8d17a94921ff3005e65257a29aae5c6",
"assets/assets/ring_10.png": "f5558e2a964f00c8cd5e22a5b811ed19",
"assets/assets/ring_2.png": "b9aff9a517196fd01ae989c966559c8d",
"assets/assets/ring_3.png": "29410e45e6122ab2bedb84a76356c2dc",
"assets/assets/ring_4.png": "b1c6eb1a54a50adfb9acfd7956f35faf",
"assets/assets/ring_5.png": "09ff28a5cd7d4e07d30f11b97b6a28ce",
"assets/assets/ring_6.png": "87cfc7a217cf27c4729a2c146cbbbe6b",
"assets/assets/ring_7.png": "1007e10ac7c2701d96942ddee2d7f825",
"assets/assets/ring_8.png": "40c3be94b3360b8909f9bf82affcbbd0",
"assets/assets/ring_9.png": "1b80295b252407ea198c4d3e9ae4b6bb",
"assets/assets/Search.png": "61fd6a1dae4fcccda77b9880a3f54de2",
"assets/assets/secondScreen.png": "536e65030294830a90dcdd9f1c6b65f7",
"assets/assets/service.png": "c9787c68c0cf8abb53108bda462a34c9",
"assets/assets/shoeman_1.png": "b8af2c374b1c60b6147af8256dec4fb3",
"assets/assets/shoeman_10.png": "7889367af2734fda7d00022248ee176f",
"assets/assets/shoeman_2.png": "ddf6a7e76a9ca83beba5abd97d736cbb",
"assets/assets/shoeman_3.png": "e4480f6000370a2304b2e721b6e51d50",
"assets/assets/shoeman_4.png": "dca6d822825ff0e2518290654a87dbac",
"assets/assets/shoeman_5.png": "43be0e68e9bf0b3c0ad41a74c46023d9",
"assets/assets/shoeman_6.png": "514981263a3e6bcb77048f9bad173bdb",
"assets/assets/shoeman_7.png": "4073833224e30ef745c6ea9befb9417b",
"assets/assets/shoeman_8.png": "b0f9278cf846d74b8c87d6bc5d18dc13",
"assets/assets/shoeman_9.png": "9a3a93fd3ed1a71b4ef8e38641246789",
"assets/assets/sign-out-alt.svg": "a7847a7f663504dc0e2749efe3c0d473",
"assets/assets/thirdScreen.png": "987f50e01efa2a98bf759c6001c7404c",
"assets/assets/user-plumber.svg": "823730a845be9496cd32890f86387d72",
"assets/assets/womanshoe_1.png": "ce9cf556ec28cee7cdc2656c644123b4",
"assets/assets/womanshoe_10.png": "fd3ed3a1e221cf0b48367818a7b1fdf3",
"assets/assets/womanshoe_2.png": "c8d60424046075c7a5debf551976d194",
"assets/assets/womanshoe_3.png": "2c95de04d6576b7996809dca5285abab",
"assets/assets/womanshoe_4.png": "993589c54d7df8cc3fb1a11b4e853f81",
"assets/assets/womanshoe_5.png": "f6f57e6a413ec97f6cf724a3c04f36aa",
"assets/assets/womanshoe_6.png": "8d849214def7cb7c2d2c0cf422b3fc93",
"assets/assets/womanshoe_7.png": "ac5fb2d6e57ffff4c52c8ab41015c574",
"assets/assets/womanshoe_8.png": "bb9e11934a27d79d7a34346d2758227d",
"assets/assets/womanshoe_9.png": "8449796960f2a4c768831208b82df224",
"assets/FontManifest.json": "7c8eb5d4369066d36d8050154c1af682",
"assets/fonts/MaterialIcons-Regular.otf": "cab89cbb85e638fef4b9ec34014caa97",
"assets/NOTICES": "95653eaf8c0c3953beb48b816fe759fa",
"assets/packages/cupertino_icons/assets/CupertinoIcons.ttf": "89ed8f4e49bcdfc0b5bfc9b24591e347",
"assets/packages/fluttertoast/assets/toastify.css": "a85675050054f179444bc5ad70ffc635",
"assets/packages/fluttertoast/assets/toastify.js": "56e2c9cedd97f10e7e5f1cebd85d53e3",
"assets/shaders/ink_sparkle.frag": "4096b5150bac93c41cbc9b45276bd90f",
"canvaskit/canvaskit.js": "eb8797020acdbdf96a12fb0405582c1b",
"canvaskit/canvaskit.wasm": "73584c1a3367e3eaf757647a8f5c5989",
"canvaskit/chromium/canvaskit.js": "0ae8bbcc58155679458a0f7a00f66873",
"canvaskit/chromium/canvaskit.wasm": "143af6ff368f9cd21c863bfa4274c406",
"canvaskit/skwasm.js": "87063acf45c5e1ab9565dcf06b0c18b8",
"canvaskit/skwasm.wasm": "2fc47c0a0c3c7af8542b601634fe9674",
"canvaskit/skwasm.worker.js": "bfb704a6c714a75da9ef320991e88b03",
"favicon.png": "c9787c68c0cf8abb53108bda462a34c9",
"flutter.js": "59a12ab9d00ae8f8096fffc417b6e84f",
"icons/Icon-192.png": "ac9a721a12bbc803b44f645561ecb1e1",
"icons/Icon-512.png": "96e752610906ba2a93c65f8abe1645f1",
"icons/Icon-maskable-192.png": "c457ef57daa1d16f64b27b786ec2ea3c",
"icons/Icon-maskable-512.png": "301a7604d45b3e739efc881eb04896ea",
"index.html": "930d6d3829acbcf9ebe4d4795820ea26",
"/": "930d6d3829acbcf9ebe4d4795820ea26",
"main.dart.js": "1ab8b3749ae1efb842a6558e109eb8e9",
"manifest.json": "f6af14018d684eea352c824a82902f3d",
"version.json": "b16c4d7f8e2422ae47955b0446254364"};
// The application shell files that are downloaded before a service worker can
// start.
const CORE = ["main.dart.js",
"index.html",
"assets/AssetManifest.json",
"assets/FontManifest.json"];

// During install, the TEMP cache is populated with the application shell files.
self.addEventListener("install", (event) => {
  self.skipWaiting();
  return event.waitUntil(
    caches.open(TEMP).then((cache) => {
      return cache.addAll(
        CORE.map((value) => new Request(value, {'cache': 'reload'})));
    })
  );
});
// During activate, the cache is populated with the temp files downloaded in
// install. If this service worker is upgrading from one with a saved
// MANIFEST, then use this to retain unchanged resource files.
self.addEventListener("activate", function(event) {
  return event.waitUntil(async function() {
    try {
      var contentCache = await caches.open(CACHE_NAME);
      var tempCache = await caches.open(TEMP);
      var manifestCache = await caches.open(MANIFEST);
      var manifest = await manifestCache.match('manifest');
      // When there is no prior manifest, clear the entire cache.
      if (!manifest) {
        await caches.delete(CACHE_NAME);
        contentCache = await caches.open(CACHE_NAME);
        for (var request of await tempCache.keys()) {
          var response = await tempCache.match(request);
          await contentCache.put(request, response);
        }
        await caches.delete(TEMP);
        // Save the manifest to make future upgrades efficient.
        await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
        // Claim client to enable caching on first launch
        self.clients.claim();
        return;
      }
      var oldManifest = await manifest.json();
      var origin = self.location.origin;
      for (var request of await contentCache.keys()) {
        var key = request.url.substring(origin.length + 1);
        if (key == "") {
          key = "/";
        }
        // If a resource from the old manifest is not in the new cache, or if
        // the MD5 sum has changed, delete it. Otherwise the resource is left
        // in the cache and can be reused by the new service worker.
        if (!RESOURCES[key] || RESOURCES[key] != oldManifest[key]) {
          await contentCache.delete(request);
        }
      }
      // Populate the cache with the app shell TEMP files, potentially overwriting
      // cache files preserved above.
      for (var request of await tempCache.keys()) {
        var response = await tempCache.match(request);
        await contentCache.put(request, response);
      }
      await caches.delete(TEMP);
      // Save the manifest to make future upgrades efficient.
      await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
      // Claim client to enable caching on first launch
      self.clients.claim();
      return;
    } catch (err) {
      // On an unhandled exception the state of the cache cannot be guaranteed.
      console.error('Failed to upgrade service worker: ' + err);
      await caches.delete(CACHE_NAME);
      await caches.delete(TEMP);
      await caches.delete(MANIFEST);
    }
  }());
});
// The fetch handler redirects requests for RESOURCE files to the service
// worker cache.
self.addEventListener("fetch", (event) => {
  if (event.request.method !== 'GET') {
    return;
  }
  var origin = self.location.origin;
  var key = event.request.url.substring(origin.length + 1);
  // Redirect URLs to the index.html
  if (key.indexOf('?v=') != -1) {
    key = key.split('?v=')[0];
  }
  if (event.request.url == origin || event.request.url.startsWith(origin + '/#') || key == '') {
    key = '/';
  }
  // If the URL is not the RESOURCE list then return to signal that the
  // browser should take over.
  if (!RESOURCES[key]) {
    return;
  }
  // If the URL is the index.html, perform an online-first request.
  if (key == '/') {
    return onlineFirst(event);
  }
  event.respondWith(caches.open(CACHE_NAME)
    .then((cache) =>  {
      return cache.match(event.request).then((response) => {
        // Either respond with the cached resource, or perform a fetch and
        // lazily populate the cache only if the resource was successfully fetched.
        return response || fetch(event.request).then((response) => {
          if (response && Boolean(response.ok)) {
            cache.put(event.request, response.clone());
          }
          return response;
        });
      })
    })
  );
});
self.addEventListener('message', (event) => {
  // SkipWaiting can be used to immediately activate a waiting service worker.
  // This will also require a page refresh triggered by the main worker.
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
    return;
  }
  if (event.data === 'downloadOffline') {
    downloadOffline();
    return;
  }
});
// Download offline will check the RESOURCES for all files not in the cache
// and populate them.
async function downloadOffline() {
  var resources = [];
  var contentCache = await caches.open(CACHE_NAME);
  var currentContent = {};
  for (var request of await contentCache.keys()) {
    var key = request.url.substring(origin.length + 1);
    if (key == "") {
      key = "/";
    }
    currentContent[key] = true;
  }
  for (var resourceKey of Object.keys(RESOURCES)) {
    if (!currentContent[resourceKey]) {
      resources.push(resourceKey);
    }
  }
  return contentCache.addAll(resources);
}
// Attempt to download the resource online before falling back to
// the offline cache.
function onlineFirst(event) {
  return event.respondWith(
    fetch(event.request).then((response) => {
      return caches.open(CACHE_NAME).then((cache) => {
        cache.put(event.request, response.clone());
        return response;
      });
    }).catch((error) => {
      return caches.open(CACHE_NAME).then((cache) => {
        return cache.match(event.request).then((response) => {
          if (response != null) {
            return response;
          }
          throw error;
        });
      });
    })
  );
}
