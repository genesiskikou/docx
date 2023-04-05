<?php
/*
Plugin Name: DOCX to Wordpress article (Simple)
Description: A plugin for converting DOCX files to Wordpress Article, with options for selecting an account, category, and post status.
Version: 1.0.0
Author: Latlas
Text Domain: dtwa
*/

if ( ! function_exists( 'dtwa_fs' ) ) {
  // Create a helper function for easy SDK access.
  function dtwa_fs() {
      global $dtwa_fs;

      if ( ! isset( $dtwa_fs ) ) {
          // Include Freemius SDK.
          require_once dirname(__FILE__) . '/freemius/start.php';

          $dtwa_fs = fs_dynamic_init( array(
              'id'                  => '11996',
              'slug'                => 'docx-to-wordpress-article',
              'type'                => 'plugin',
              'public_key'          => 'pk_b05487c29b467825cf27b73163b70',
              'is_premium'          => true,
              'is_premium_only'     => true,
              'has_addons'          => false,
              'has_paid_plans'      => true,
              'is_org_compliant'    => false,
              'menu'                => array(
                  'support'        => false,
                  'if you want buy a license key click here' => 'https://checkout.freemius.com/mode/dialog/plugin/11996/plan/20393/',
              ),
          ) );
      }

      return $dtwa_fs;
  }

  // Init Freemius.
  dtwa_fs();
  // Signal that SDK was initiated.
  do_action( 'dtwa_fs_loaded' );
}

require_once('vendor/autoload.php');
require_once('parser.php');
require( dirname(__FILE__) . '/../../../wp-load.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/image.php' );
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html as Parser;
use PhpOffice\PhpWord\Element\Image;

function dtwa_load_textdomain() {
  load_plugin_textdomain( 'dtwa', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'dtwa_load_textdomain' );
function docx_to_html_importer_menu() {
  add_menu_page('DOCX to Wordpress Article', 'DOCX to Wordpress Article', 'manage_options', 'docx-to-wordpress-article', 'docx_to_html_importer_options', plugins_url( 'DOCX to Wordpress Article Free/images/icone-import-docx3.png'), 81);
}
add_action('admin_menu', 'docx_to_html_importer_menu');

function docx_to_html_importer_options() {
  if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
  }

  if (isset($_POST['submit'], $_FILES['docx_files'])) {
    $files = $_FILES['docx_files'];
    $account_id = $_POST['account'];
    $category_id = $_POST['category'];
    $status = $_POST['status'];

    // Image 
    $featured_img = $_FILES['featured_image'];

    // Boucle pour ajouter l'image sur wordpress
    if ($featured_img['name'] != ""){
      $upload = wp_handle_upload( $_FILES[ 'featured_image' ], array( 'test_form' => false ) );

      $attachment_id = wp_insert_attachment(
        array(
          'guid'           => $upload[ 'url' ],
          'post_mime_type' => $upload[ 'type' ],
          'post_title'     => basename( $upload[ 'file' ] ),
          'post_content'   => '',
          'post_status'    => 'inherit',
        ),
        $upload[ 'file' ]
      );

      if( is_wp_error( $attachment_id ) || ! $attachment_id ) {
        wp_die( 'Upload error.' );
      }

      wp_update_attachment_metadata($attachment_id,
        wp_generate_attachment_metadata( $attachment_id, $upload[ 'file' ] )
      );
    }
  
    foreach ($files['name'] as $key => $value) {
      if ($files['error'][$key] === 0) {
        $file = array(
          'name' => $files['name'][$key],
          'type' => $files['type'][$key],
          'tmp_name' => $files['tmp_name'][$key],
          'error' => $files['error'][$key],
          'size' => $files['size'][$key]
        );
  
        $move_result = move_uploaded_file($file['tmp_name'], WP_CONTENT_DIR . '/uploads/' . $file['name']);

        if ($move_result) {
          $docx_file = WP_CONTENT_DIR . '/uploads/' . $file['name'];

            // Charge le DOCX avec phpWord
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($docx_file);

            // Récupère toutes les images du document
            $images = $phpWord->getImagesFromDocument();

            // Transforme le document en HTML avec parser.php
            $parser = new Parser;
            $parser->setFile($docx_file);
            $html = $parser->toHtml();

            // Parcourt toutes les images récupérées et remplace leur chemin dans le HTML généré
            foreach ($images as $image) {
              $html=str_replace($image->getSrc(), WP_CONTENT_DIR . '/uploads/' . $image->getFileName(), $html);
            }

            // Récupère le titre
            $pattern = "/<h1>(.*?)<\/h1>/";
            preg_match_all($pattern, $html, $matches);
            $titre = $matches[1][0];

            // Supprime le titre du content
            $titre_a_remplacer = "<h1>".$titre."</h1>";
            $content = str_replace($titre_a_remplacer, '', $html);

            // Supprime les tags du content et du titre
            $content = supprimeStyle($content);
            $titre = strip_tags($titre, '');

            // Publie l'article
            $post_id = wp_insert_post(array(
                'post_author' => $account_id,
                'post_category' => array($category_id),
                'post_content' => $content,
                'post_title' => $titre,
                'post_type' => 'post',
                'post_status' => $status
            ));

          wp_set_post_categories($post_id,$_POST['category'],false);

          // Mettre l'image sur le post WP avec l'id du post
          if ($featured_img['name'] != ""){
            set_post_thumbnail( $post_id, $attachment_id );
          }
        }
      }
        
        
        
        
        
    }
    echo '<div class="updated notice is-dismissible"> <p>'. __("DOCX imported successfully!", "dtwa") . '</p> </div>';
    }
    
  

    ?>
      <h1 id="titre"><?= __('DOCX to Wordpress Article', 'dtwa') ?></h1>

      <?php if (dtwa_fs()->is_plan__premium_only('pro_version')){?>

      <form method="post" action="" enctype="multipart/form-data">
        <table class="form-table-t">

        <div>
          <tr valign="middle">
            <th scope="row">
              <label for="docx_files"><?= __('DOCX Files', 'dtwa') ?></label>
          <td>
            <div class="messageDiv1"></div>
          <!-- Input file avec la propriété 'hidden' pour le masquer -->
          <input type="file" class="input-file" name="docx_files[]" id="docx_files" multiple hidden>
          
          <!-- Bouton qui permettra d'acceder au fichier -->
          <button type="button" class="input-file" id="docx_files_button"><?= __('Select your files', 'dtwa') ?></button>
          <span id="selected-file">No file</span>
          <script>
              let inputElement = document.getElementById("docx_files");
              let customButton = document.getElementById("docx_files_button");
              let selectedFile = document.getElementById("selected-file");
              
              customButton.addEventListener("click", function() {
                  inputElement.click();
              });

              inputElement.addEventListener("change", function() {
              if (inputElement.files.length > 0) {
                selectedFile.innerHTML = 'you drop ' + inputElement.files.length + ' files';
                console.log(inputElement.files.length);
              } else {
                selectedFile.innerHTML = "No file selected";
              }
            });
          </script>
          
            <p class="sous-titre"><?= __('(Drag and drop or click on select)', 'dtwa') ?></p>
          </td>
        </tr>
        </div>

        <div>
          <tr valign="middle">
            <th scope="row">
              <div class="box">
              <label><?= __('Featured Image', 'dtwa') ?></label>&nbsp;
              <div id="help-button-container">
                <button id="help-button">?</button>
                <div id="help-message" style="display:none;">
                  <div id="close-button-container">
                    <button id="close-button">X</button>
                  </div>
                  <p class="sous-titre"><?= __('Select an image if you want to add a default front <br> page image to all your documents.', 'dtwa') ?></p>
                </div>
              </div>
              <script>
                document.getElementById("help-button").addEventListener("click", function(event){
                  event.preventDefault();
                  var helpMessage = document.getElementById("help-message");
                  helpMessage.style.display = "block";
                });
                document.getElementById("close-button").addEventListener("click", function(event){
                  event.preventDefault();
                  var helpMessage = document.getElementById("help-message");
                  helpMessage.style.display = "none";
                });
              </script>
              </div>
              <p class="sous-titre"><?= __('(Optionnal)', 'dtwa') ?></p>
            </th>
          <td>
          <div id="message"></div>
          <!-- Input file avec la propriété 'hidden' pour le masquer -->
          <input type="file" class="input-file" name="featured_image" id="featured_image" multiple hidden>

          <!-- Bouton qui permettra d'acceder au fichier-->
          <button type="button" class="input-file" id="picture_button"><?= __('Select your picture', 'dtwa') ?></button>
          <span id="selected-picture">No file</span>

          <!-- Script JavaScript pour sélectionner des fichiers en cliquant sur le bouton -->
          <script>
              let fileinput = document.getElementById("featured_image");
              let custombutton = document.querySelector("#picture_button");
              let fileNameLabel = document.querySelector("#selected-picture");
              let messageDiv = document.querySelector("#message");

              // Ajout d'un événement au clic sur le bouton personnalisé
              custombutton.addEventListener("click", function() {
                  fileinput.click();
              });

              // Ajout d'un événement à l'input de type file pour mettre à jour le label du nom de fichier
              fileinput.addEventListener("change", function() {
                  if (fileinput.value) {
                      let fileName = fileinput.files[0].name;
                      fileNameLabel.textContent = fileName;
                      let fileExtension = fileName.split('.').pop().toLowerCase();

                      if (fileExtension === "png" || fileExtension === "jpg" || fileExtension === "jpeg") {
                          messageDiv.innerHTML = `<p class="success"><?= __('File selected', 'dtwa') ?> : ${fileName}</p>`;
                      } else {
                          messageDiv.innerHTML = `<p class="error"><?= __('File type not supported. Please select a PNG or JPG file.', 'dtwa') ?></p>`;
                      }
                  } else {
                      fileNameLabel.textContent = "No file selected";
                      messageDiv.innerHTML = "";
                  }
              });
          </script>
            <p class="sous-titre"><?= __('(Drag and drop or click on select)', 'dtwa') ?></p>
          </td>
        </tr>
        </div>

        <tr valign="middle">
          <th scope="row">
            <label for="account"><?= __('Account', 'dtwa') ?></label>
          </th>
          <td>
            <select name="account" id="account">
              <?php
              $users = get_users();
              foreach ($users as $user) {
                echo '<option value="' . $user->ID . '">' . $user->display_name . '</option>';
              }
              ?>
            </select>
          </td>
        </tr>
        <tr valign="middle">
          <th scope="row">
            <label for="category"><?= __('Category', 'dtwa') ?></label>
          </th>
          <td>
          <select name="category[]" multiple required>
              <?php
              $args = array(
                  'hide_empty' => 0,
                  'orderby' => 'name',
                  'parent' => 0
              );
              $categories = get_categories( $args );
              foreach ( $categories as $category ) {
                  echo '<option value="' . $category->cat_ID . '">' . $category->cat_name . '</option>';
                  $args = array(
                      'hide_empty' => 0,
                      'orderby' => 'name',
                      'parent' => $category->cat_ID
                  );
                  $subcategories = get_categories( $args );
                  foreach ( $subcategories as $subcategory ) {
                      echo '<option value="' . $subcategory->cat_ID . '">&nbsp;&nbsp;&nbsp;' . $subcategory->cat_name . '</option>';
                  
                  $args = array(
                    'hide_empty' => 0,
                    'orderby' => 'name',
                    'parent' => $subcategory->cat_ID
                );
                $subsubcategories = get_categories( $args );
                foreach ( $subsubcategories as $subsubcategory ) {
                    echo '<option value="' . $subsubcategory->cat_ID . '">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $subsubcategory->cat_name . '</option>';
                }
              }
              }
              ?>
          </select>
          <p class="sous-titre"><?= __('(Cltr + click to select multiple categories)', 'dtwa') ?></p>

          </td>
        </tr>
        <tr valign="middle">
          <th scope="row">
            <label for="status"><?= __('Status', 'dtwa') ?></label>
          </th>
          <td>
            <select name="status" id="status">
              <option value="draft"><?= __('Draft', 'dtwa') ?></option>
              <option value="pending"><?= __('Pending Review', 'dtwa') ?></option>
              <option value="publish"><?= __('Publish', 'dtwa') ?></option>
            </select>
          </td>
        </tr>
      </table>
      <br>
      <div class="hide">
        <input type="submit" name="submit" id="submit-t"  hidden/>
        </div>
        <button type="button" class="submit" id="submit-t2"><?= __('Import', 'dtwa') ?></button>
        <!-- Script JavaScript pour envoyer des fichiers en cliquant sur le bouton -->
        <script>
        let filesubmit = document.getElementById("submit-t");
        let custombutton2 = document.querySelector("#submit-t2");                               
        let messageDiv1 = document.querySelector(".messageDiv1");
        let selectedFile1 = document.getElementById("selected-file");
              

        
        // Ajout d'un événement à l'input de type file pour mettre à jour le label du nom de fichier
        inputElement.addEventListener("change", function() {
                  if (inputElement.value) {
                    let fileName = inputElement.files[0].name;
                      selectedFile1.textContent = fileName;
                      let fileExtension2 = fileName.split('.').pop().toLowerCase();
                      if (fileExtension2 === "docx" ) {
                        messageDiv1.innerHTML = "";
                        custombutton2.addEventListener("click", function() {
                        filesubmit.click();
                        });
                      } else {
                          messageDiv1.innerHTML = `<p class="error">File type not supported. Please select a DOCX document</p>`;
                      }
                  } else {
                      fileNameLabel.textContent = "No file selected";
                      messageDiv1.innerHTML = "";
                  }
              });

</script>
    </form>
    
  <?php
  }
}

function supprimeStyle($string) {
  return preg_replace('/(<[^>]+) style=".*?"/i', '$1', $string);     
}

function add_styles() {
  $plugin_url = plugin_dir_url( __FILE__ );
  wp_enqueue_style( 'style',  $plugin_url . "/style.css");
}
add_action( 'admin_print_styles', 'add_styles' );
?>