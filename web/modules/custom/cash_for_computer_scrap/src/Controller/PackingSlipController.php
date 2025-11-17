<?php

namespace Drupal\cash_for_computer_scrap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use Mpdf\Mpdf;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\NodeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file\Entity\File;


class PackingSlipController extends ControllerBase {

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  protected ConfigFactoryInterface $configFactoryService;

  public function __construct(RendererInterface $renderer, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory) {
    $this->renderer = $renderer;
    $this->fileSystem = $file_system;
    $this->configFactoryService = $config_factory;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('file_system'),
      $container->get('config.factory')
    );
  }

  /**
   * Generates and downloads the packing slip PDF.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object to generate the PDF for.
   * @return \Symfony\Component\HttpFoundation\Response
   *   A Symfony response object containing the PDF file.
   */
  public function generatePdf($node) {

  $config = $this->configFactoryService->get('cash_for_computer_scrap.settings');

  $logo = $config->get('logo');
  $fid = $logo[0];



  $file = File::load($fid);

  $url = '';

if ($file) {
  $url = $file->createFileUrl();
}

  // Build the render array
  $render_array = [
    '#theme' => 'packing_slip',
    '#node' => $node,
    '#config' => $config->getRawData(),
    '#logo' => $url,
  ];

  $html = $this->renderer->renderPlain($render_array);

  // For testing in browser - remove after testing

    // return $render_array;

  return new Response($html, 200, ['Content-Type' => 'text/html']);



    // // 1. Fetch Order Data (Replace with real data fetching from database or commerce)
    // // --- START DUMMY DATA ---
    // $order_data = (object) [
    //   'date' => time(),
    //   'address' => (object) [
    //     'name' => 'John Doe',
    //     'street' => '123 Drupal Lane',
    //     'city' => 'Anytown',
    //     'zip' => '12345',
    //   ],
    //   'items' => [
    //     (object) ['sku' => 'A101', 'name' => 'Widget Pro', 'qty' => 1],
    //     (object) ['sku' => 'B202', 'name' => 'Gadget Lite', 'qty' => 3],
    //   ],
    // ];
    // // --- END DUMMY DATA ---

    // // 2. Build the render array for the Twig template.
    // $render_array = [
    //   '#theme' => 'packing_slip', // Define this in mymodule.module with hook_theme()
    //   '#order' => $order_data,
    // ];

    // // 3. Render the HTML content from the Twig template.
    // $html = $this->renderer->renderPlain($render_array);

    // // 4. PDF Generation using mPDF.
    // try {
    //   $mpdf = new Mpdf([
    //     // Use Drupal's temporary directory for mPDF temporary files.
    //     'tempDir' => $this->fileSystem->getTempDirectory(),
    //   ]);

    //   $mpdf->WriteHTML($html);

    //   // 'S' returns the document as a string for download.
    //   $filename = "packing-slip-{$node_id}.pdf";
    //   $pdf_data = $mpdf->Output($filename, 'S');

    //   // 5. Return the PDF as a Response object.
    //   return new Response($pdf_data, 200, [
    //     'Content-Type' => 'application/pdf',
    //     'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    //     'Content-Length' => strlen($pdf_data),
    //     'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
    //   ]);
    // }
    // catch (\Exception $e) {
    //   // Log the error and show a user-friendly message.
    //   $this->messenger()->addError($this->t('Could not generate PDF: @message', ['@message' => $e->getMessage()]));
    //   $this->logger('cash_for_computer_scrap')->error('PDF Generation failed: @message', ['@message' => $e->getMessage()]);
    //   return new Response((string) $this->t('Error generating PDF.'), 500);
    // }
  }

}