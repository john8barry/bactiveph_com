<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$products_data = json_decode(base64_decode('W3sic2t1IjogIllZOTE0MSIsICJuYW1lIjogIlRoZSBDb3VydCBEcmVzcyIsICJwcmljZSI6ICIxOTUwIiwgImNhdGVnb3J5IjogIlBpY2tsZWJhbGwgRHJlc3NlcyIsICJ0YWdzIjogWyJUaGUgQ291cnQgRWRpdCJdLCAiY29sb3JzIjogWyJDb3VydCBJdm9yeSIsICJXaXN0ZXJpYSIsICJTdG9uZSJdLCAic2hvcnRfZGVzYyI6ICJNb3ZlIGZyZWVseSwgbG9vayBlZmZvcnRsZXNzLiBPdXIgc2lnbmF0dXJlIHBsZWF0ZWQgcGlja2xlYmFsbCBkcmVzcyBwYWlycyBhIGZsYXR0ZXJpbmcgc2lsaG91ZXR0ZSB3aXRoIHNlcmlvdXMgcGVyZm9ybWFuY2UuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBTd2VhdC13aWNraW5nLCBidXR0ZXJ5LXNvZnQgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyAoNFxcXCIgaW5zZWFtKSB3aXRoIGJhbGwgcG9ja2V0IFx1MjAyMiBCdWlsdC1pbiBsaWdodC1zdXBwb3J0IGJyYSB3aXRoIHJlbW92YWJsZSBwYWRzIFx1MjAyMiBQbGVhdGVkIHNraXJ0IHRoYXQgaG9sZHMgaXRzIHNoYXBlIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiQmFsbCBwb2NrZXQiLCAiQnVpbHQtaW4gYnJhIiwgIlBvY2tldHMiXSwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY1IGNtLCB3ZWFycyBNLiJ9LCB7InNrdSI6ICJZWTg3OTMiLCAibmFtZSI6ICJUaGUgUmFsbHkgRHJlc3MiLCAicHJpY2UiOiAiMTg5MCIsICJjYXRlZ29yeSI6ICJQaWNrbGViYWxsIERyZXNzZXMiLCAidGFncyI6IFsiVGhlIENvdXJ0IEVkaXQiXSwgImNvbG9ycyI6IFsiTWlkbmlnaHQiXSwgInNob3J0X2Rlc2MiOiAiT3Blbi1iYWNrIGVhc2UsIG9uLWNvdXJ0IGNvbmZpZGVuY2UuIEEgc2Nvb3AtYmFjayBkcmVzcyB3aXRoIGEgc2lkZSB0aWUgdGhhdCBza2ltcyBhbmQgZmxhdHRlcnMuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBTd2VhdC13aWNraW5nIFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBiYWxsIHBvY2tldCBcdTIwMjIgRmxhdHRlcmluZyBzY29vcCBiYWNrIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiQmFsbCBwb2NrZXQiXSwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY4IGNtLCB3ZWFycyBNLiJ9LCB7InNrdSI6ICJZWTQwMDEiLCAibmFtZSI6ICJUaGUgQnViYmxlIERyZXNzIiwgInByaWNlIjogIjIxOTAiLCAiY2F0ZWdvcnkiOiAiUGlja2xlYmFsbCBEcmVzc2VzIiwgInRhZ3MiOiBbIlRoZSBDb3VydCBFZGl0Il0sICJjb2xvcnMiOiBbIkNvdXJ0IEl2b3J5IiwgIlNha3VyYSIsICJQb3dkZXIiLCAiV2lzdGVyaWEiLCAiT255eCJdLCAic2hvcnRfZGVzYyI6ICJUaGUgZHJlc3MgZXZlcnlvbmUgYXNrcyBhYm91dC4gQSBzY3VscHRlZCBib2RpY2UgbWVldHMgYSBwbGF5ZnVsIGJ1YmJsZSBoZW0gXHUyMDE0IGNvdXJ0LXJlYWR5LCBjYWZcdTAwZTktcmVhZHkuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBiYWxsIHBvY2tldCBcdTIwMjIgQnVpbHQtaW4gYnJhLCByZW1vdmFibGUgcGFkcyBcdTIwMjIgU3RhdGVtZW50IGJ1YmJsZSBoZW0iLCAicGFfZmVhdHVyZXMiOiBbIkJ1aWx0LWluIHNob3J0cyIsICJCYWxsIHBvY2tldCIsICJCdWlsdC1pbiBicmEiXSwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY1IGNtLCB3ZWFycyBNLiJ9LCB7InNrdSI6ICJBUzUwMTkiLCAibmFtZSI6ICJUaGUgTWF0Y2ggRHJlc3MiLCAicHJpY2UiOiAiMjQ1MCIsICJjYXRlZ29yeSI6ICJQaWNrbGViYWxsIERyZXNzZXMiLCAidGFncyI6IFsiVGhlIENvdXJ0IEVkaXQiXSwgImNvbG9ycyI6IFsiQ291cnQgSXZvcnkiXSwgInNob3J0X2Rlc2MiOiAiWmlwIHVwIGFuZCBzZXJ2ZS4gQSBtb2Rlcm4gemlwLWZyb250IGRyZXNzIHdpdGggY2xlYW4gc3RyaXBlZCB0cmltIHRoYXQgYnJpbmdzIGNsYXNzaWMgY291bnRyeS1jbHViIHN0eWxlIGludG8gdGhlIG1vZGVybiBnYW1lLiIsICJmZWF0dXJlcyI6ICJDb3VydFNvZnRcdTIxMjIgZm91ci13YXkgc3RyZXRjaCBcdTIwMjIgWmlwLWZyb250IGJvZGljZSBmb3IgYWRqdXN0YWJsZSBhaXJmbG93IFx1MjAyMiBTdHJpcGVkIHRyaW0gZGV0YWlsaW5nIFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBiYWxsIHBvY2tldCBcdTIwMjIgQnVpbHQtaW4gbGlnaHQtc3VwcG9ydCBicmEiLCAicGFfZmVhdHVyZXMiOiBbIkJ1aWx0LWluIHNob3J0cyIsICJCYWxsIHBvY2tldCIsICJCdWlsdC1pbiBicmEiXSwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY2IGNtLCB3ZWFycyBNLiJ9LCB7InNrdSI6ICJBUzUwMjgiLCAibmFtZSI6ICJUaGUgU2VydmUgRHJlc3MiLCAicHJpY2UiOiAiMjQ1MCIsICJjYXRlZ29yeSI6ICJQaWNrbGViYWxsIERyZXNzZXMiLCAidGFncyI6IFsiVGhlIENvdXJ0IEVkaXQiXSwgImNvbG9ycyI6IFsiU2FrdXJhIl0sICJzaG9ydF9kZXNjIjogIkJ1aWx0IGZvciB5b3VyIHN0cm9uZ2VzdCBzd2luZy4gQSBzdHJlYW1saW5lZCByYWNlcmJhY2sgZHJlc3Mgd2l0aCBhIGdyYWNlZnVsbHkgcGxlYXRlZCBza2lydC4iLCAiZmVhdHVyZXMiOiAiQ291cnRTb2Z0XHUyMTIyIGZvdXItd2F5IHN0cmV0Y2ggXHUyMDIyIFJhY2VyYmFjayBkZXNpZ24gZm9yIGZ1bGwgbW9iaWxpdHkgXHUyMDIyIEVsZWdhbnQgcGxlYXRlZCBza2lydCBcdTIwMjIgQnVpbHQtaW4gc2hvcnRzIHdpdGggYmFsbCBwb2NrZXQgXHUyMDIyIEJ1aWx0LWluIGJyYSB3aXRoIHJlbW92YWJsZSBwYWRzIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiQmFsbCBwb2NrZXQiLCAiQnVpbHQtaW4gYnJhIl0sICJmaXQiOiAiVHJ1ZSB0byBzaXplIChBc2lhbiBmaXQpLCBTXHUyMDEzWEwuIE1vZGVsIGlzIDE2NSBjbSwgd2VhcnMgTS4ifSwgeyJza3UiOiAiWVk5MTg3IiwgIm5hbWUiOiAiVGhlIEV5ZWxldCBEcmVzcyIsICJwcmljZSI6ICIyNDUwIiwgImNhdGVnb3J5IjogIlBpY2tsZWJhbGwgRHJlc3NlcyIsICJ0YWdzIjogWyJUaGUgQ291cnQgRWRpdCJdLCAiY29sb3JzIjogWyJPbnl4IiwgIkNvdXJ0IEl2b3J5IiwgIlBvd2RlciJdLCAic2hvcnRfZGVzYyI6ICJBIGZyZXNoIHRha2Ugb24gY291cnQgc3R5bGUuIEZlYXR1cmluZyBhIGRyb3Atd2Fpc3Qgc2lsaG91ZXR0ZSBhbmQgdW5pcXVlIHRleHR1cmVkIGV5ZWxldCBmYWJyaWMgdGhhdCBicmVhdGhlcyBhcyB5b3UgbW92ZS4iLCAiZmVhdHVyZXMiOiAiQnJlYXRoYWJsZSB0ZXh0dXJlZCBleWVsZXQgZmFicmljIFx1MjAyMiBEcm9wLXdhaXN0IHBsZWF0ZWQgc2tpcnQgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyB3aXRoIGJhbGwgcG9ja2V0IFx1MjAyMiBCdWlsdC1pbiBsaWdodC1zdXBwb3J0IGJyYSIsICJwYV9mZWF0dXJlcyI6IFsiQnVpbHQtaW4gc2hvcnRzIiwgIkJhbGwgcG9ja2V0IiwgIkJ1aWx0LWluIGJyYSJdLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjcgY20sIHdlYXJzIE0uIn0sIHsic2t1IjogIkFTODE4IiwgIm5hbWUiOiAiVGhlIFZhcnNpdHkgRHJlc3MiLCAicHJpY2UiOiAiMjY1MCIsICJjYXRlZ29yeSI6ICJQaWNrbGViYWxsIERyZXNzZXMiLCAidGFncyI6IFsiVGhlIENvdXJ0IEVkaXQiXSwgImNvbG9ycyI6IFsiU2FnZXdvb2QiXSwgInNob3J0X2Rlc2MiOiAiUHJlcHB5IG1lZXRzIHBlcmZvcm1hbmNlLiBBIGNvbGxhcmVkIGRyZXNzIHdpdGggY3Jpc3AgY29udHJhc3QgdHJpbSBhbmQgYSBwbGVhdGVkIHNraXJ0IHRoYXQgbWVhbnMgYnVzaW5lc3MuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBDb2xsYXJlZCwgY29udHJhc3QtdHJpbSBkZXRhaWwgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyB3aXRoIGJhbGwgcG9ja2V0IFx1MjAyMiBCdWlsdC1pbiBzdXBwb3J0IGJyYSIsICJwYV9mZWF0dXJlcyI6IFsiQnVpbHQtaW4gc2hvcnRzIiwgIkJhbGwgcG9ja2V0IiwgIkJ1aWx0LWluIGJyYSJdLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjcgY20sIHdlYXJzIE0uIn0sIHsic2t1IjogIkFTODExIiwgIm5hbWUiOiAiVGhlIEFjZSBEcmVzcyIsICJwcmljZSI6ICIyNjUwIiwgImNhdGVnb3J5IjogIlBpY2tsZWJhbGwgRHJlc3NlcyIsICJ0YWdzIjogWyJUaGUgQ291cnQgRWRpdCJdLCAiY29sb3JzIjogWyJBcHJpY290IiwgIkNsYXkgUmVkIl0sICJzaG9ydF9kZXNjIjogIkZlbWluaW5lLCBmbGFyZWQsIGFuZCBmaWVyY2UuIENvbnRyYXN0IHRyaW0gaGlnaGxpZ2h0cyBhIGJlYXV0aWZ1bGx5IGZsYXJlZCBza2lydCB0aGF0IG1vdmVzIHdpdGggZXZlcnkgdm9sbGV5LiIsICJmZWF0dXJlcyI6ICJDb3VydFNvZnRcdTIxMjIgZm91ci13YXkgc3RyZXRjaCBcdTIwMjIgQ29udHJhc3QgdHJpbSBzdHlsaW5nIFx1MjAyMiBGbGFyZWQsIGZsdWlkIHNraXJ0IFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBiYWxsIHBvY2tldCBcdTIwMjIgQnVpbHQtaW4gYnJhIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiQmFsbCBwb2NrZXQiLCAiQnVpbHQtaW4gYnJhIl0sICJmaXQiOiAiVHJ1ZSB0byBzaXplIChBc2lhbiBmaXQpLCBTXHUyMDEzWEwuIE1vZGVsIGlzIDE2NiBjbSwgd2VhcnMgTS4ifSwgeyJza3UiOiAiREsyMS0wMTUiLCAibmFtZSI6ICJUaGUgRXZlcnlkYXkgU2tvcnQiLCAicHJpY2UiOiAiOTkwIiwgImNhdGVnb3J5IjogIlNrb3J0cyIsICJ0YWdzIjogWyJFdmVyeWRheSBBY3RpdmUiXSwgImNvbG9ycyI6IFsiU2FrdXJhIiwgIlN0b25lIiwgIkNvdXJ0IEl2b3J5IiwgIk9ueXgiXSwgInNob3J0X2Rlc2MiOiAiVGhlIHNrb3J0IHlvdSdsbCByZWFjaCBmb3IgZGFpbHkuIEEgc2ltcGxlLCBmbGF0dGVyaW5nIGZsYXJlZCBjdXQgdGhhdCB0cmFuc2l0aW9ucyBlZmZvcnRsZXNzbHkgZnJvbSB0aGUgY291cnQgdG8gY29mZmVlLiIsICJmZWF0dXJlcyI6ICJDb3VydFNvZnRcdTIxMjIgZm91ci13YXkgc3RyZXRjaCBcdTIwMjIgRmxhdHRlcmluZyBmbGFyZWQgc2lsaG91ZXR0ZSBcdTIwMjIgQnVpbHQtaW4gc2hvcnRzIChubyByaWRlLXVwKSBcdTIwMjIgU2VjdXJlIGJhbGwgcG9ja2V0IiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiQmFsbCBwb2NrZXQiLCAiUG9ja2V0cyJdLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBIaWdoLXdhaXN0ZWQuIn0sIHsic2t1IjogIkRLMjUxMjA0NDQ1IiwgIm5hbWUiOiAiVGhlIFBsZWF0ZWQgU2tvcnQiLCAicHJpY2UiOiAiMTA5MCIsICJjYXRlZ29yeSI6ICJTa29ydHMiLCAidGFncyI6IFsiRXZlcnlkYXkgQWN0aXZlIl0sICJjb2xvcnMiOiBbIk9ueXgiXSwgInNob3J0X2Rlc2MiOiAiU3dpbmcgZnJlZWx5LCBzdGF5IGNvdmVyZWQuIEEgaGlnaC13YWlzdCBwbGVhdGVkIHNrb3J0IHdpdGggc2VjdXJlIGJ1aWx0LWluIHNob3J0cy4iLCAiZmVhdHVyZXMiOiAiQ291cnRTb2Z0XHUyMTIyIGZvdXItd2F5IHN0cmV0Y2ggXHUyMDIyIEJ1aWx0LWluIHNob3J0cyAobm8gcmlkZS11cCkgd2l0aCBwb2NrZXQgXHUyMDIyIEhpZ2gsIGZsYXR0ZXJpbmcgd2Fpc3RiYW5kIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiQmFsbCBwb2NrZXQiLCAiUG9ja2V0cyJdLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjUgY20sIHdlYXJzIE0uIn0sIHsic2t1IjogIkRLMjQwNDIwMzY3IiwgIm5hbWUiOiAiVGhlIEZsb3cgU2tvcnQiLCAicHJpY2UiOiAiMTA5MCIsICJjYXRlZ29yeSI6ICJTa29ydHMiLCAidGFncyI6IFsiRXZlcnlkYXkgQWN0aXZlIl0sICJjb2xvcnMiOiBbIkNvdXJ0IEl2b3J5Il0sICJzaG9ydF9kZXNjIjogIkR5bmFtaWMgZGVzaWduIGZvciBkeW5hbWljIHBsYXkuIEEgcGlwZWQgZGlhZ29uYWwgc2VhbSBnaXZlcyB0aGlzIHNrb3J0IGEgYmVhdXRpZnVsIHNlbnNlIG9mIG1vdGlvbi4iLCAiZmVhdHVyZXMiOiAiQ291cnRTb2Z0XHUyMTIyIGZvdXItd2F5IHN0cmV0Y2ggXHUyMDIyIERpYWdvbmFsIHNlYW0gd2l0aCBwaXBpbmcgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyAobm8gcmlkZS11cCkgXHUyMDIyIFNlY3VyZSBiYWxsIHBvY2tldCIsICJwYV9mZWF0dXJlcyI6IFsiQnVpbHQtaW4gc2hvcnRzIiwgIkJhbGwgcG9ja2V0IiwgIlBvY2tldHMiXSwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gSGlnaC13YWlzdGVkLiJ9LCB7InNrdSI6ICJEMzEiLCAibmFtZSI6ICJUaGUgQnJlZXplIFNrb3J0IiwgInByaWNlIjogIjEyNTAiLCAiY2F0ZWdvcnkiOiAiU2tvcnRzIiwgInRhZ3MiOiBbIkV2ZXJ5ZGF5IEFjdGl2ZSJdLCAiY29sb3JzIjogWyJOaWdodCBJbmRpZ28iLCAiTWVhZG93IEdyZWVuIiwgIlNha3VyYSIsICJPbnl4IiwgIkNvdXJ0IEl2b3J5Il0sICJzaG9ydF9kZXNjIjogIlN0YXkgY29vbCB3aGVuIHRoZSBtYXRjaCBoZWF0cyB1cC4gQ3JhZnRlZCBpbiBvdXIgdWx0cmEtbGlnaHQgQnJlZXplS25pdFx1MjEyMiBmYWJyaWMgd2l0aCBhIGNsYXNzaWMgQS1saW5lIGN1dC4iLCAiZmVhdHVyZXMiOiAiQnJlZXplS25pdFx1MjEyMiB1bHRyYS1saWdodHdlaWdodCBmYWJyaWMgXHUyMDIyIENsYXNzaWMgQS1saW5lIHNpbGhvdWV0dGUgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyBcdTIwMjIgQmFsbCBwb2NrZXQiLCAicGFfZmVhdHVyZXMiOiBbIkJ1aWx0LWluIHNob3J0cyIsICJCYWxsIHBvY2tldCIsICJQb2NrZXRzIl0sICJmaXQiOiAiVHJ1ZSB0byBzaXplIChBc2lhbiBmaXQpLCBTXHUyMDEzWEwuIn0sIHsic2t1IjogIkQyOSIsICJuYW1lIjogIlRoZSBDb3VydCBTa29ydCIsICJwcmljZSI6ICIxMjkwIiwgImNhdGVnb3J5IjogIlNrb3J0cyIsICJ0YWdzIjogWyJFdmVyeWRheSBBY3RpdmUiXSwgImNvbG9ycyI6IFsiTWlkbmlnaHQiLCAiQ291cnQgSXZvcnkiLCAiT2lsIEJsdWUiLCAiU2FrdXJhIiwgIkdyZWVuIEphc3BlciJdLCAic2hvcnRfZGVzYyI6ICJPdXIgbW9zdCBwcmVtaXVtIHBsZWF0ZWQgc2tvcnQuIERlc2lnbmVkIHdpdGggY3Jpc3AsIHNoYXJwIHBsZWF0cyB0aGF0IGhvbGQgdGhlaXIgc2hhcGUgdGhyb3VnaCBldmVyeSB3YXNoIGFuZCB3ZWFyLiIsICJmZWF0dXJlcyI6ICJQcmVtaXVtIHN0cnVjdHVyZSwgc29mdCBmZWVsIFx1MjAyMiBTaGFycCwgbGFzdGluZyBwbGVhdHMgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyBcdTIwMjIgQmFsbCBwb2NrZXQiLCAicGFfZmVhdHVyZXMiOiBbIkJ1aWx0LWluIHNob3J0cyIsICJCYWxsIHBvY2tldCIsICJQb2NrZXRzIl0sICJmaXQiOiAiVHJ1ZSB0byBzaXplIChBc2lhbiBmaXQpLCBTXHUyMDEzWEwuIEhpZ2gtd2Fpc3RlZC4ifSwgeyJza3UiOiAiV1gxNTA2IiwgIm5hbWUiOiAiVGhlIFJpYmJlZCBUYW5rIiwgInByaWNlIjogIjg5NSIsICJjYXRlZ29yeSI6ICJUb3BzICYgVGFua3MiLCAidGFncyI6IFsiRXZlcnlkYXkgQWN0aXZlIl0sICJjb2xvcnMiOiBbIlNha3VyYSJdLCAic2hvcnRfZGVzYyI6ICJZb3VyIGV2ZXJ5ZGF5IE1WUC4gQSByaWJiZWQgcmFjZXJiYWNrIGNyb3AgdGhhdCBsYXllcnMgb3ZlciBhbnkgYnJhIGFuZCB3ZWFycyBzb2xvIGp1c3QgYXMgd2VsbC4iLCAiZmVhdHVyZXMiOiAiQnV0dGVyeS1zb2Z0IHJpYmJlZCBrbml0IFx1MjAyMiBSYWNlcmJhY2sgY3V0IGZvciBmcmVlIG1vdmVtZW50IFx1MjAyMiBDcm9wIGxlbmd0aCBwYWlycyB3aXRoIGhpZ2gtd2Fpc3Qgc2tvcnRzL2xlZ2dpbmdzIiwgInBhX2ZlYXR1cmVzIjogW10sICJmaXQiOiAiVHJ1ZSB0byBzaXplIChBc2lhbiBmaXQpLCBTXHUyMDEzWEwuIE1vZGVsIGlzIDE2NyBjbSwgd2VhcnMgTS4ifSwgeyJza3UiOiAiQURXWDEyNDkiLCAibmFtZSI6ICJUaGUgU3RyYXBweSBCcmEiLCAicHJpY2UiOiAiOTUwIiwgImNhdGVnb3J5IjogIlNwb3J0cyBCcmFzIiwgInRhZ3MiOiBbIkV2ZXJ5ZGF5IEFjdGl2ZSJdLCAiY29sb3JzIjogWyJBcHJpY290IiwgIlBvd2RlciIsICJPbnl4IiwgIkNvdXJ0IEl2b3J5Il0sICJzaG9ydF9kZXNjIjogIlN1cHBvcnQgdGhhdCBsb29rcyBzdHVubmluZy4gQSBtdWx0aS1zdHJhcCBiYWNrIGRlc2lnbiBvZmZlcmluZyBtZWRpdW0gc3VwcG9ydCBmb3IgdGhlIGNvdXJ0IGFuZCBiZXlvbmQuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBFbGVnYW50IG11bHRpLXN0cmFwIGJhY2sgXHUyMDIyIE1lZGl1bSBzdXBwb3J0IFx1MjAyMiBSZW1vdmFibGUgcGFkcyIsICJwYV9mZWF0dXJlcyI6IFtdLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiJ9LCB7InNrdSI6ICJBRENLMTU4MyIsICJuYW1lIjogIlRoZSBTY3VscHQgTGVnZ2luZyIsICJwcmljZSI6ICIxMDkwIiwgImNhdGVnb3J5IjogIkxlZ2dpbmdzIiwgInRhZ3MiOiBbIkV2ZXJ5ZGF5IEFjdGl2ZSJdLCAiY29sb3JzIjogWyJBbG1vbmQiLCAiU3RvbmUiXSwgInNob3J0X2Rlc2MiOiAiTGlmdCwgc21vb3RoLCBhbmQgc3VwcG9ydC4gQSBjb250b3VyaW5nLCAxMDAlIHNxdWF0LXByb29mIGxlZ2dpbmcgZGVzaWduZWQgZm9yIGhpZ2ggcGVyZm9ybWFuY2UuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBTcXVhdC1wcm9vZiBhbmQgb3BhcXVlIFx1MjAyMiBIaWdoLXdhaXN0IGNvbnRvdXJpbmcgZml0IFx1MjAyMiBTZWFtbGVzcyBmcm9udCAobm8gY2FtZWwgdG9lKSIsICJwYV9mZWF0dXJlcyI6IFsiU3F1YXQtcHJvb2YiXSwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gSGlnaC13YWlzdGVkLCBmdWxsIGxlbmd0aC4ifSwgeyJza3UiOiAiQ0sxMjM3IiwgIm5hbWUiOiAiVGhlIENvcmUgTGVnZ2luZyIsICJwcmljZSI6ICIxMTkwIiwgImNhdGVnb3J5IjogIkxlZ2dpbmdzIiwgInRhZ3MiOiBbIkV2ZXJ5ZGF5IEFjdGl2ZSJdLCAiY29sb3JzIjogWyJXaXN0ZXJpYSJdLCAic2hvcnRfZGVzYyI6ICJZb3VyIGVzc2VudGlhbCBmb3VuZGF0aW9uLiBBIGJlYXV0aWZ1bGx5IHNpbXBsZSwgYnV0dGVyeS1zb2Z0IGhpZ2gtd2Fpc3QgbGVnZ2luZyB0aGF0IHN0YXlzIHB1dC4iLCAiZmVhdHVyZXMiOiAiQ291cnRTb2Z0XHUyMTIyIGZvdXItd2F5IHN0cmV0Y2ggXHUyMDIyIFVsdHJhLXNvZnQsIHNlY29uZC1za2luIGZlZWwgXHUyMDIyIEhpZ2gsIHNlY3VyZSB3YWlzdGJhbmQgXHUyMDIyIFNxdWF0LXByb29mIiwgInBhX2ZlYXR1cmVzIjogWyJTcXVhdC1wcm9vZiJdLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBIaWdoLXdhaXN0ZWQuIn0sIHsic2t1IjogIkMzNiIsICJuYW1lIjogIlRoZSBIYWx0ZXIgU2V0IiwgInByaWNlIjogIjI0OTAiLCAiY2F0ZWdvcnkiOiAiU2V0cyIsICJ0YWdzIjogWyJUaGUgUmFsbHkgU2V0Il0sICJjb2xvcnMiOiBbIlNha3VyYSIsICJDb3VydCBJdm9yeSIsICJBbG1vbmQiLCAiQmxvb20iLCAiUG93ZGVyIiwgIk9ueXgiXSwgInNob3J0X2Rlc2MiOiAiT25lIGRlY2lzaW9uLCBoZWFkLXRvLXRvZS4gQSBmbGF0dGVyaW5nIGhhbHRlciBicmEgYW5kIGhpZ2gtd2Fpc3QgbGVnZ2luZ3MgdGhhdCBtb3ZlIGFzIG9uZS4iLCAiZmVhdHVyZXMiOiAiQ291cnRTb2Z0XHUyMTIyIGZvdXItd2F5IHN0cmV0Y2gsIHNxdWF0LXByb29mIFx1MjAyMiBIYWx0ZXIgYnJhIHdpdGggcmVtb3ZhYmxlIHBhZHMgXHUyMDIyIEhpZ2gtd2Fpc3QgbGVnZ2luZ3Mgd2l0aCBzaWRlIHBvY2tldHMgXHUyMDIyIE1peC1hbmQtbWF0Y2ggd2l0aCBUaGUgQ291cnQgRWRpdCIsICJwYV9mZWF0dXJlcyI6IFsiUG9ja2V0cyIsICJTcXVhdC1wcm9vZiJdLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjYgY20sIHdlYXJzIE0uIn1d'), true);

// Clean up "Test Product"
$test_prod = get_page_by_title('Test Product', OBJECT, 'product');
if ($test_prod) {
    wp_delete_post($test_prod->ID, true);
}

// 1. Setup Global Attributes
$attributes = [
    'pa_colour' => ['Court Ivory', 'Midnight', 'Onyx', 'Sakura', 'Powder', 'Sagewood', 'Wisteria', 'Stone', 'Apricot', 'Almond', 'Bloom', 'Clay Red', 'Night Indigo', 'Meadow Green', 'Oil Blue', 'Green Jasper'],
    'pa_size' => ['S', 'M', 'L', 'XL'],
    'pa_features' => ['Built-in shorts', 'Ball pocket', 'Built-in bra', 'Pockets', 'UPF50+', 'Squat-proof']
];

foreach ($attributes as $slug => $terms) {
    $attr_id = wc_attribute_taxonomy_id_by_name($slug);
    if (!$attr_id) {
        $attr_data = array(
            'name'         => ucfirst(str_replace('pa_', '', $slug)),
            'slug'         => str_replace('pa_', '', $slug),
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        );
        $attr_id = wc_create_attribute($attr_data);
    }
    register_taxonomy($slug, apply_filters('woocommerce_taxonomy_objects_' . $slug, array('product')), apply_filters('woocommerce_taxonomy_args_' . $slug, array('hierarchical' => true, 'show_ui' => false, 'query_var' => true, 'rewrite' => false)));
    
    foreach ($terms as $term) {
        if (!term_exists($term, $slug)) {
            wp_insert_term($term, $slug);
        }
    }
}

// 2. Setup Categories & Tags
$categories = ['Pickleball Dresses', 'Skorts', 'Tops & Tanks', 'Sports Bras', 'Leggings', 'Sets', 'Pickleball Paddles'];
foreach ($categories as $cat) {
    if (!term_exists($cat, 'product_cat')) wp_insert_term($cat, 'product_cat');
}
$tags = ['The Court Edit', 'The Rally Set', 'Everyday Active'];
foreach ($tags as $tag) {
    if (!term_exists($tag, 'product_tag')) wp_insert_term($tag, 'product_tag');
}

// Function to upload image
function bactive_upload_image($filename, $title) {
    $upload_dir = wp_upload_dir();
    if (!file_exists(ABSPATH . 'product_images/' . $filename)) return false;
    $image_data = file_get_contents(ABSPATH . 'product_images/' . $filename);
    if (!$image_data) return false;
    
    $file = $upload_dir['path'] . '/' . $filename;
    file_put_contents($file, $image_data);
    
    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($title),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file);
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
    update_post_meta($attach_id, '_wp_attachment_image_alt', $title);
    return $attach_id;
}

// 3. Create Products
foreach ($products_data as $pd) {
    $existing = wc_get_product_id_by_sku($pd['sku']);
    if ($existing) {
        $product = wc_get_product($existing);
    } else {
        $product = new WC_Product_Variable();
        $product->set_name($pd['name']);
        $product->set_sku($pd['sku']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
    }
    
    // Set content
    if (isset($pd['short_desc'])) {
        $excerpt = $pd['short_desc'] . "<br><br><ul>";
        $features = explode('•', $pd['features']);
        foreach ($features as $feat) {
            $excerpt .= "<li>" . trim($feat) . "</li>";
        }
        $excerpt .= "</ul><br><strong>Fit & Sizing:</strong> " . $pd['fit'];
        $product->set_short_description($excerpt);
    }
    
    // Set category
    $term = get_term_by('name', $pd['category'], 'product_cat');
    if ($term) $product->set_category_ids([$term->term_id]);
    
    // Set tags
    $tag_ids = [];
    foreach ($pd['tags'] as $tag_name) {
        $t = get_term_by('name', $tag_name, 'product_tag');
        if ($t) $tag_ids[] = $t->term_id;
    }
    $product->set_tag_ids($tag_ids);
    
    // Set Attributes
    $prod_attributes = [];
    
    // Size attribute
    $attr_size = new WC_Product_Attribute();
    $attr_size->set_id(wc_attribute_taxonomy_id_by_name('pa_size'));
    $attr_size->set_name('pa_size');
    $size_options = [];
    foreach(['S', 'M', 'L', 'XL'] as $s_name) {
        $t = get_term_by('name', $s_name, 'pa_size');
        if($t) $size_options[] = $t->term_id;
    }
    $attr_size->set_options($size_options);
    $attr_size->set_position(0);
    $attr_size->set_visible(true);
    $attr_size->set_variation(true);
    $prod_attributes[] = $attr_size;
    
    // Colour attribute
    $attr_colour = new WC_Product_Attribute();
    $attr_colour->set_id(wc_attribute_taxonomy_id_by_name('pa_colour'));
    $attr_colour->set_name('pa_colour');
    $color_options = [];
    foreach($pd['colors'] as $c_name) {
        $t = get_term_by('name', $c_name, 'pa_colour');
        if($t) $color_options[] = $t->term_id;
    }
    $attr_colour->set_options($color_options);
    $attr_colour->set_position(1);
    $attr_colour->set_visible(true);
    $attr_colour->set_variation(true);
    $prod_attributes[] = $attr_colour;
    
    // Features attribute
    if (isset($pd['pa_features']) && !empty($pd['pa_features'])) {
        $attr_feat = new WC_Product_Attribute();
        $attr_feat->set_id(wc_attribute_taxonomy_id_by_name('pa_features'));
        $attr_feat->set_name('pa_features');
        $feat_opts = [];
        foreach($pd['pa_features'] as $f_name) {
            $t = get_term_by('name', $f_name, 'pa_features');
            if($t) $feat_opts[] = $t->term_id;
        }
        $attr_feat->set_options($feat_opts);
        $attr_feat->set_position(2);
        $attr_feat->set_visible(true); // Visible on product page / filters
        $attr_feat->set_variation(false);
        $prod_attributes[] = $attr_feat;
    }
    
    $product->set_attributes($prod_attributes);
    $product_id = $product->save();
    
    // Images lookup
    $files = glob(ABSPATH . "product_images/*" . $pd['sku'] . "*.png");
    $gallery_ids = [];
    if ($files) {
        foreach ($files as $index => $file) {
            $filename = basename($file);
            $img_id = bactive_upload_image($filename, $pd['name']);
            if ($img_id) {
                if ($index === 0) {
                    $product->set_image_id($img_id);
                } else {
                    $gallery_ids[] = $img_id;
                }
            }
        }
        if (!empty($gallery_ids)) {
            $product->set_gallery_image_ids($gallery_ids);
        }
    }
    $product->save();
    
    // Create or Update Variations
    // We map variations to images linearly if we have multiple images.
    $main_img_id = $product->get_image_id();
    $all_imgs = [];
    if ($main_img_id) $all_imgs[] = $main_img_id;
    $all_imgs = array_merge($all_imgs, $gallery_ids);
    
    $existing_vars = $product->get_children();
    foreach ($existing_vars as $vid) {
        $v = wc_get_product($vid);
        if ($v) $v->delete(true);
    }
    
    $color_idx = 0;
    foreach ($pd['colors'] as $color) {
        // Find matching image based on index
        $var_img_id = $main_img_id;
        if (isset($all_imgs[$color_idx])) {
            $var_img_id = $all_imgs[$color_idx];
        } else if (count($all_imgs) > 0) {
            $var_img_id = $all_imgs[count($all_imgs) - 1]; // fallback to last
        }
        
        foreach (['S', 'M', 'L', 'XL'] as $size) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes([
                'pa_size' => sanitize_title($size),
                'pa_colour' => sanitize_title($color)
            ]);
            $variation->set_regular_price($pd['price']);
            $variation->set_price($pd['price']);
            $variation->set_manage_stock(false);
            $variation->set_stock_status('instock');
            if ($var_img_id) {
                $variation->set_image_id($var_img_id);
            }
            $variation->save();
        }
        $color_idx++;
    }
}

echo "Catalog updated successfully!";
?>
