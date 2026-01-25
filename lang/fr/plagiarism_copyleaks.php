<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'plagiarism_copyleaks', language 'fr', version '3.5'.
 *
 * @package     plagiarism_copyleaks
 * @category    string
 * @copyright   1999 Martin Dougiamas and contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['claccountconfig'] = 'Configuration du compte Copyleaks';
$string['claccountkey'] = 'Copyleaks clé';
$string['copyleaks:enable'] = 'Activer Copyleaks';
$string['clenablebydefualt'] = 'Activer Copyleaks pour tous les modules par défaut';
$string['copyleaks:viewfullreport'] = 'Voir le rapport Web';
$string['copyleaks:resubmitfailedscans'] = 'Renvoyer les analyses échouées (niveau module)';
$string['copyleaks:adminresubmitfailedscans'] = 'Renvoyer les analyses échouées (niveau système)';
$string['claccountsecret'] = 'Copyleaks secrète';
$string['cladminconfig'] = 'Copyleaks configuration du plugin de plagiat';
$string['cladminconfigsavesuccess'] = 'Copyleaks les paramètres de plagiat ont été enregistrés avec succès.';
$string['clallowstudentaccess'] = 'Autoriser les étudiants à accéder aux rapports de plagiat';
$string['clallowstudentaccessbydefault'] = 'Autoriser l’étudiant à voir le rapport de similarité de Copyleaks';
$string['clallowstudentaccessbydefault_help'] = 'Si cette option est activée par défaut, les étudiants auront automatiquement accès au rapport de similarité Copyleaks pour toutes les activités prises en charge.';
$string['clapisubmissionerror'] = 'Copyleaks a renvoyé une erreur lors de la tentative d\'envoi du fichier pour soumission -';
$string['clapiurl'] = 'URL de l\'API Copyleaks';
$string['clcheatingdetected'] = 'Tricherie détectée, ouvrir le rapport pour en savoir plus';
$string['clcheatingdetectedtxt'] = 'Tricherie détectée';
$string['cldisabledformodule'] = 'Le plugin Copyleaks est désactivé pour ce module.';
$string['cldraftsubmit'] = 'Soumettre les fichiers uniquement lorsque les étudiants cliquent sur le bouton Soumettre';
$string['cldraftsubmit_help'] = 'Cette option n\'est disponible que si "Demander aux étudiants de cliquer sur le bouton d\'envoi" est Oui';
$string['clenable'] = 'Activer Copyleaks';
$string['clenablebydefault'] = 'Activer l’intégration de Copyleaks par défaut';
$string['clenablebydefault_help'] = 'L’activation par défaut de l’intégration de Copyleaks garantit que la détection du plagiat est automatiquement activée pour toutes les nouvelles activités prenant en charge la vérification du plagiat.';
$string['clenablemodulefor'] = 'Activer les fuites de copie pour {$a}';
$string['clfailtosavedata'] = 'Échec de l\'enregistrement des données Copyleaks';
$string['clgenereportimmediately'] = 'Générez des rapports immédiatement';
$string['clgenereportonduedate'] = 'Générer des rapports à la date d\'échéance';
$string['clresubmitfailed'] = 'Essayer à nouveau';
$string['clresubmitfailedscans'] = 'Renvoyer les analyses échouées';
$string['clresubmitfailedscansdisabled'] = 'Renvoyer les analyses échouées (disponible une fois par heure)';
$string['clinserterror'] = 'Erreur lors de la tentative d\'insertion d\'enregistrements dans la base de données';
$string['clinvalidkeyorsecret'] = 'Clé ou secret invalide';
$string['cllogsheading'] = 'Journaux';
$string['cllogstab'] = 'Journaux';
$string['clnopageaccess'] = 'Vous n\'avez pas accès à cette page.';
$string['clopenfullscreen'] = 'Ouvrir en plein écran';
$string['clopenreport'] = 'Cliquez pour ouvrir le rapport Copyleaks';
$string['clplagiarised'] = 'Score de similarité';
$string['clplagiarisefailed'] = 'Manquée';
$string['clplagiarisequeued'] = 'Analyse de plagiat prévue à {$a}';
$string['clplagiarisescanning'] = 'Recherche de plagiat...';
$string['clpluginconfigurationtab'] = 'Configurations';
$string['clpluginintro'] = 'Le vérificateur de plagiat Copyleaks est une solution complète et précise qui aide les enseignants et les étudiants à vérifier si leur contenu est original.<br>Pour plus d\'informations sur la configuration et l\'utilisation du plugin, veuillez vérifier <a target="_blank" href="https://lti.copyleaks.com/guides/select-moodle-integration">nos guides</a>.</br></br></br>';
$string['clpoweredbycopyleaks'] = 'Propulsé par Copyleaks';
$string['clreportgenspeed'] = 'Quand générer un rapport?';
$string['clreportpagetitle'] = 'Copyleaks Rapport';
$string['clscansettingspagebtntxt'] = 'Modifier les paramètres de numérisation';
$string['clanalyticsbtntxt'] = "Afficher les analyses";
$string['clmodulescansettingstxt'] = "Modifier les paramètres de numérisation";
$string['cldisablesettingstooltip'] = "Veuillez patienter jusqu'à ce que Copyleaks établisse un nouveau module";
$string['clscoursesettings'] = 'Paramètres de fuites de copie';
$string['clsendqueuedsubmissions'] = 'Plugin de plagiat Copyleaks - gérer les fichiers en file d\'attente';
$string['clsendresubmissionsfiles'] = "Plugin de plagiat Copyleaks - gérer les résultats soumis à nouveau";
$string['clsyncoriginalityscore'] = "Plugin de plagiat Copyleaks - gérer la synchronisation des scores de plagiat";
$string['clsyncconfigtask'] = "Plugin de plagiat Copyleaks - gérer la synchronisation des configurations";
$string['clsendrequestqueue'] = "Plugin de plagiat Copyleaks - gérer les demandes de relance en file d'attente";
$string['clupserteulausers'] = "Plugin de plagiat Copyleaks - gérer la mise à jour des utilisateurs d'acceptation eula";
$string['clbackgroundtask'] = "Plugin de plagiat Copyleaks - gérer les tâches en arrière-plan";
$string['clstudentdisclosure'] = 'Divulgation des étudiants';
$string['clstudentdisclosure_help'] = 'Ce texte sera affiché à tous les étudiants sur la page de téléchargement de fichiers.';
$string['clstudentdisclosuredefault'] = '<span>En soumettant vos fichiers, vous acceptez la politique de confidentialité du service de détection de plagiat</span><a target="_blank" href="https://copyleaks.com/legal/privacypolicy">politique de confidentialité</a>';
$string['clstudentdagreedtoeula'] = '<span>Vous avez déjà accepté le service de détection de plagiat </span><a target="_blank" href="https://copyleaks.com/legal/privacypolicy">politique de confidentialité</a>';
$string['cltaskfailedconnecting'] = 'La connexion à Copyleaks ne peut pas être établie, erreur : {$a}';
$string['clupdateerror'] = 'Erreur lors de la tentative de mise à jour des enregistrements dans la base de données';
$string['clupdatereportscores'] = 'Plugin de plagiat Copyleaks - gérer la mise à jour du score de similarité de la vérification du plagiat';
$string['clduplicatecoursemodules'] = "Plugin de plagiat Copyleaks - gérer la duplication des modules de cours";
$string['copyleaks'] = 'Copyleaks';
$string['pluginname'] = 'Plugin de plagiat Copyleaks';
$string['clplagiarismdetectionthresholds'] = 'Seuils de détection du plagiat';
$string['claicontentdetectionthresholds'] = 'Seuils de détection du contenu IA';
$string['cllowcontentmatchthreshold'] = 'Gravité faible :';
$string['clmidcontentmatchthreshold'] = 'Gravité modérée :';
$string['clhighcontentmatchthreshold'] = 'Gravité élevée :';
$string['clplagiarismdetectionthresholds_help'] = '<b>Codage couleur de la gravité du plagiat :</b>
<ul>
<li><span style="color:green;"><b>Vert :</b></span> Gravité faible.</li>
<li><span style="color:orange;"><b>Jaune :</b></span> Gravité modérée.</li>
<li><span style="color:red;"><b>Rouge :</b></span> Gravité élevée.</li>
</ul> ';
$string['claicontentdetectionthresholds_help'] = '<b>Codage couleur de la gravité du contenu IA :</b>
<ul>
<li><span style="color:green;"><b>Vert :</b></span> Gravité faible.</li>
<li><span style="color:orange;"><b>Jaune :</b></span> Gravité modérée.</li>
<li><span style="color:red;"><b>Rouge :</b></span> Gravité élevée.</li>
</ul> ';
$string['cldefaultsettings'] = 'Paramètres par défaut de Copyleaks';


$string['claicontentscore'] = 'Score du contenu IA';
$string['clplagiarismscore'] = 'Score de plagiat';
$string['clwritingfeedbackissues'] = 'Rédaction de corrections';
$string['clwritingfeedbackcontentscheduled'] = 'Rédaction de corrections';
$string['clscanfailedbtn'] = "ÉCHEC DE L'ANALYSE";
$string['cltryagainbtn'] = 'Essayer à nouveau';
$string['cltryagainmsg'] = "Soumettez-vous à nouveau à l'analyse Copyleaks";
$string['clscaninprogress'] = "L'analyse est en cours...";
$string['clscheduledintime'] = 'Prévu: {$d}';
$string['claicontentscheduled'] = 'Analyse du contenu IA';
$string['clplagiarismcontentscheduled'] = 'Analyse du plagiat';
$string['cldownloadreport'] = 'Télécharger le rapport PDF';
$string['clopenreport'] = 'Ouvrir la page du rapport';
$string['clcopyreporturl'] = "Copiez le lien de la page du rapport dans le presse-papiers";
$string['cltimesoon'] = 'bientôt';
$string['cltimeminutes'] = 'compte-rendu';
$string['cltimehours'] = 'heures';
$string['cltimedays'] = 'jours';
$string['cltimemonths'] = 'mois';
$string['cltimemin'] = 'dans';
$string['clpendingduplication'] = 'En attente de duplication';
$string['clfailedduplication'] = 'Échec de la duplication';

$string['clreportupdatefailed'] = 'Échec de la mise à jour du rapport';
$string['clsendnotificationfailed'] = 'Échec de l’envoi de la notification';
$string['filenotfound'] = 'Fichier introuvable';

$string['privacy:metadata:core_files'] = 'Copyleaks stocke les fichiers qui ont été téléchargés sur Moodle pour former une soumission Copyleaks.';
$string['privacy:metadata:plagiarism_copyleaks_client'] = 'Afin de s\'intégrer à un Copyleaks, certaines données utilisateur doivent être échangées avec Copyleaks.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_creationtime'] = 'L\'heure de création du module est envoyée à Copyleaks à des fins d\'identification.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_id'] = 'L\'identifiant du module est envoyé à Copyleaks à des fins d\'identification.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_name'] = 'Le nom du module est envoyé à Copyleaks à des fins d\'identification.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_type'] = 'Le type de module est envoyé à Copyleaks à des fins d\'identification.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_content'] = 'The submission content is sent to Copyleaks for scan processing.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_name'] = 'Le nom de la soumission est envoyé à Copyleaks à des fins d\'identification.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_type'] = 'Le type de soumission est envoyé à Copyleaks à des fins d\'identification.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_userId'] = 'L\'ID utilisateur de soumission est envoyé à Copyleaks à des fins d\'identification.';
$string['privacy:metadata:plagiarism_copyleaks_files'] = 'Informations qui relient une soumission Moodle à une soumission Copyleaks.';
$string['privacy:metadata:plagiarism_copyleaks_files:lastmodified'] = 'Un horodatage indiquant quand l\'utilisateur a modifié sa soumission pour la dernière fois.';
$string['privacy:metadata:plagiarism_copyleaks_files:similarityscore'] = 'Le score de similarité de la soumission.';
$string['privacy:metadata:plagiarism_copyleaks_files:submitter'] = 'L\'ID de l\'utilisateur qui a effectué la soumission.';
$string['privacy:metadata:plagiarism_copyleaks_files:userid'] = 'ID de l\'utilisateur qui est le propriétaire de la soumission.';

$string['classignment'] = 'Devoir';
$string['clquizzes'] = 'Quiz';
$string['clforums'] = 'Forum';
$string['clworkshop'] = 'Atelier';
