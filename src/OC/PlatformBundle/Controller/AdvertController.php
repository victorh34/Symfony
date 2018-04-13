<?php

// src/OC/PlatformBundle/Controller/AdvertController.php

namespace OC\PlatformBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use OC\PlatformBundle\Entity\Advert;
use OC\PlatformBundle\Entity\Image;
use OC\PlatformBundle\Entity\Application;
use OC\PlatformBundle\Entity\AdvertSkill;
use OC\PlatformBundle\Form\AdvertType;
use Symfony\Component\HttpFoundation\Response; //Pour pouvoir donner une réponse 
use Symfony\Component\HttpFoundation\Request; //Pour récupérer les paramètres de l url
use Symfony\Component\HttpFoundation\RedirectResponse; //Pour la rédirection des pages.
use Symfony\Component\HttpFoundation\JsonResponse; //Pour utiliser le raccourci JsonResponse
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException; // Pour utiliser le NotFoundHttpException
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AdvertController extends Controller
{
	public function menuAction($limit)
	{
	    $em = $this->getDoctrine()->getManager();

	    $listAdverts = $em->getRepository('OCPlatformBundle:Advert')->findBy(
	      array(),                 // Pas de critère
	      array('date' => 'desc'), // On trie par date décroissante
	      $limit,                  // On sélectionne $limit annonces
	      0                        // À partir du premier
	    );

	    return $this->render('OCPlatformBundle:Advert:menu.html.twig', array(
	      // Tout l'intérêt est ici : le contrôleur passe
	      // les variables nécessaires au template !
	      'listAdverts' => $listAdverts
	    ));
	}
    
    public function indexAction($page)
    {
	    if ($page < 1) {
	      // On déclenche une exception NotFoundHttpException, cela va afficher
	      // une page d'erreur 404 (qu'on pourra personnaliser plus tard d'ailleurs)
	      throw new NotFoundHttpException('Page "'.$page.'" inexistante.');
	    }
		$nbPerPage = 3;
		
		$listAdverts = $this->getDoctrine()
	      ->getManager()
	      ->getRepository('OCPlatformBundle:Advert')
	      ->getAdverts($page, $nbPerPage)
	    ;

		$nbPages = ceil(count($listAdverts) / $nbPerPage);
		
		if ($page > $nbPages) {
      		throw $this->createNotFoundException("La page ".$page." n'existe pas.");
    	}

    	return $this->render('OCPlatformBundle:Advert:index.html.twig', array(
    		'listAdverts' => $listAdverts,
    		'nbPages' => $nbPages,
    		'page' => $page
		));
    }

    // La route fait appel à OCPlatformBundle:Advert:view,
    // on doit donc définir la méthode viewAction.
    // On donne à cette méthode l'argument $id, pour
    // correspondre au paramètre {id} de la route
    public function viewAction($id, Request $request)
    {
	    $em = $this->getDoctrine()->getManager();
		// C est la meme chose ----------------------
  //   	$advert = $this->getDoctrine()
		//   ->getManager()
		//   ->find('OCPlatformBundle:Advert', $id)
		// ;
		//-------------------------------------------
	    // On récupère l'entité correspondante à l'id $id
    	$advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);
    	// On récupère la liste de candidatures de l'advert
	    $listApplications = $em
	      ->getRepository('OCPlatformBundle:Application')
	      ->findBy(array('advert' => $advert));
	    // On récupère la liste de skills de l'advert
	    $listAdvertSkills = $em
		  ->getRepository('OCPlatformBundle:AdvertSkill')
		  ->findBy(array('advert' => $advert))
		;
    	// $advert est donc une instance de OC\PlatformBundle\Entity\Advert
	    // ou null si l'id $id  n'existe pas, d'où ce if :
	    if (null === $advert) {
	    	throw new NotFoundHttpException("L'annonce d'id ".$id." n'existe pas.");
	    }

	    return $this->render('OCPlatformBundle:Advert:view.html.twig', array(
	    	'advert'  => $advert,
	    	'listApplications' => $listApplications,
	    	'listAdvertSkills' => $listAdvertSkills
	    ));
    }

    public function addAction(Request $request)
    {

		//-------------------------------------------------------
    	//utiliser le service antispam que j ai cree
	    // On récupère le service
	    $antispam = $this->container->get('oc_platform.antispam');
	    // Je pars du principe que $text contient le texte d'un message quelconque
		//    $text = '...';
		//    if ($antispam->isSpam($text)) {
		//      throw new \Exception('Votre message a été détecté comme spam !');
		// }
	    //-------------------------------------------------------


		// On crée un objet Advert
	    $advert = new Advert();

		$form = $this->createForm(AdvertType::class, $advert);
    	// Pour l'instant, pas de candidatures, catégories, etc., on les gérera plus tard

	    // À partir du formBuilder, on génère le formulaire
	    // $form = $formBuilder->getForm();

	    // La gestion d'un formulaire est particulière, mais l'idée est la suivante :
	    // Si la requête est en POST, c'est que le visiteur a soumis le formulaire
	    if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
        // On enregistre notre objet $advert dans la base de données, par exemple
	        $em = $this->getDoctrine()->getManager();
	        $em->persist($advert);
	        $em->flush();
    		$request->getSession()->getFlashBag()->add('notice', 'Annonce bien enregistrée.');

	     	//  $contenu = $this->renderView('OCPlatformBundle:Advert:email.txt.twig', array(
	     	//  			'pseudo' => 'vic',
		   		// 		)
	  			// );
	     	//  mail('victorh34@hotmail.com', 'Inscription OK', $contenu);

		    // Puis on redirige vers la page de visualisation de cettte annonce
	      	return $this->redirectToRoute('oc_platform_view', array('id' => $advert->getId()));
	    }

	    // S il arrive ici c est parce que soit on est en get, soit le formulaire a des valeurs erronés donc il revient ici.
	    return $this->render('OCPlatformBundle:Advert:add.html.twig', array(
      		'form' => $form->createView()));
    }

	public function editAction($id, Request $request)
	{
	    $em = $this->getDoctrine()->getManager();

	    // On récupère l'annonce $id
	    $advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);

	    if (null === $advert) {
	      throw new NotFoundHttpException("L'annonce d'id ".$id." n'existe pas.");
	    }
	    
	    // Ici, on récupérera l'annonce correspondante à $id
	    // Même mécanisme que pour l'ajout
	    if ($request->isMethod('POST')) {
	      $request->getSession()->getFlashBag()->add('notice', 'Annonce bien modifiée.');
	      return $this->redirectToRoute('oc_platform_view', array('id' => $id));
	    }
	    return $this->render('OCPlatformBundle:Advert:edit.html.twig', array('advert' => $advert));
	}

	public function deleteAction($id)
	{
	    $em = $this->getDoctrine()->getManager();
	    // On récupère l'annonce $id
	    $advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);
	    if (null === $advert) {
	      throw new NotFoundHttpException("L'annonce d'id ".$id." n'existe pas.");
	    }
	    // On boucle sur les catégories de l'annonce pour les supprimer
	    foreach ($advert->getCategories() as $category) {
	      $advert->removeCategory($category);
	    }
	    // Pour persister le changement dans la relation, il faut persister l'entité propriétaire
	    // Ici, Advert est le propriétaire, donc inutile de la persister car on l'a récupérée depuis Doctrine
	    // On déclenche la modification
	    $em->flush();

	    return $this->render('OCPlatformBundle:Advert:delete.html.twig');
	}





    // On récupère tous les paramètres de la route en arguments de la méthode
    public function viewSlugAction($slug, $year, $_format)
    {
      return new Response(
          "On pourrait afficher l'annonce correspondant au
          slug '".$slug."', créée en ".$year." et au format ".$format."."
      );
    }

#-------------------------------------
#action faite juste pour pratiquer, pas partie de l exo
    public function byeAction()
    {
        $content = $this->get('templating')->render('OCPlatformBundle:Advert:bye.html.twig', array("nom" => "Victor Papito Rico"));
        return new Response($content);
    }
#-------------------------------------
}