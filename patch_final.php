<?php
$content = file_get_contents('ihsg.php');

$search = "
      // auto-load from URL if present
      window.addEventListener('DOMContentLoaded', () => {
          setTimeout(() => {
              const urlParams = new URLSearchParams(window.location.search);
              const symFromUrl = urlParams.get('symbol');
              if (symFromUrl) {
                const selectEl = document.getElementById('symbol');
                // Ensure option exists or create it temporarily
                let exists = false;
                for (let i = 0; i < selectEl.options.length; i++) {
                    if (selectEl.options[i].value === symFromUrl) {
                        exists = true; break;
                    }
                }
                if (!exists) {
                  if (typeof symbolSelect !== 'undefined') {
                      symbolSelect.addOption({value: symFromUrl, text: symFromUrl});
                  } else {
                      const newOption = document.createElement('option');
                      newOption.value = symFromUrl;
                      newOption.text = symFromUrl;
                      selectEl.appendChild(newOption);
                  }
                }

                if (typeof symbolSelect !== 'undefined') {
                    symbolSelect.setValue(symFromUrl);
                } else {
                    selectEl.value = symFromUrl;
                }
                render(symFromUrl);
              } else if (document.getElementById('symbol').value) {
                render(document.getElementById('symbol').value);
              }
          }, 300); // 300ms buffer to ensure all core plugin states are registered
      });";

$replace = "
      window.addEventListener('DOMContentLoaded', () => {
          setTimeout(() => {
              render('^JKSE');
          }, 300);
      });
";

$content = str_replace($search, $replace, $content);
file_put_contents('ihsg.php', $content);
echo "Replaced the final leftover select symbol logic.\n";
?>
