<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use frontend\models\RegisteredVoters;
use frontend\models\VotingRecords;
use frontend\models\Candidates; // ← Hii hapa ndo ilikosekana
use yii\web\NotFoundHttpException;

class VotingController extends Controller
{
    // Weka muda wa mwisho wa kujiandikisha hapa
    public $registrationDeadline = '2025-05-10 23:59:59';

    // Kujiandikisha
   public function actionRegister()
{
    $model = new RegisteredVoters();

    // Fetch the registration deadline from the 'registered_voters' table (if available)
    $deadline = (new \yii\db\Query())
        ->select('registration_deadline')
        ->from('registered_voters')
        ->orderBy(['id' => SORT_DESC]) // or ASC if you want the first one
        ->limit(1)
        ->scalar();

    // Check if the deadline is fetched correctly
    Yii::info("Fetched Deadline: " . $deadline, __METHOD__);

    // Convert the registration deadline to DateTime object with Tanzania time zone
    $deadlineDateTime = new \DateTime($deadline, new \DateTimeZone('Africa/Dar_es_Salaam'));

    // Get the current time and convert it to DateTime object with Tanzania time zone
    $currentDateTime = new \DateTime('now', new \DateTimeZone('Africa/Dar_es_Salaam'));

    // Debugging output to check current time and registration deadline
    Yii::info("Current Time: " . $currentDateTime->format('Y-m-d H:i:s'), __METHOD__);
    Yii::info("Registration Deadline: " . $deadlineDateTime->format('Y-m-d H:i:s'), __METHOD__);

    // Check if the current time is after the registration deadline
    if ($model->load(Yii::$app->request->post())) {
        if ($currentDateTime > $deadlineDateTime) {
            Yii::$app->session->setFlash('error', 'The registration deadline has passed.');
        } else {
            // If the registration is valid, save it and redirect to the dashboard
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'You have successfully registered.');
                // Redirect to the dashboard after successful registration
                return $this->redirect(['site/dashboard']);
            } else {
                Yii::$app->session->setFlash('error', 'There was an issue with the registration process.');
            }
        }
    }

    return $this->render('register', [
        'model' => $model,
    ]);
}


    // Kupiga kura
  public function actionVote()
{
    $session = Yii::$app->session;

    // Hakikisha session iko hai
    if (!$session->isActive) {
        $session->open();
    }

    // 1. Angalia kama 'voter_id' ipo kwenye session
    $voterId = $session->get('voter_id', null);

    if ($voterId === null) {
        // Hakuna taarifa za mpiga kura kwenye session, rudisha kwa ukurasa wa verification
        Yii::$app->session->setFlash('error', 'You must verify your details first.');
        return $this->redirect(['site/details']);
    }

    // 2. Tafuta mpiga kura kutoka database
    $voter = RegisteredVoters::findOne($voterId);

    // 3. Kama mpiga kura hajapatikana au tayari amepiga kura
    if (!$voter || $voter->has_voted) {
        Yii::$app->session->setFlash('error', 'Either your details are not verified, or you have already voted.');
        return $this->redirect(['site/details']);
    }

    // 4. Pakua wagombea wote kutoka DB
    $candidates = Candidates::find()->all();

    // 5. Kagua kama form imewasilishwa
    if (Yii::$app->request->isPost) {
        $candidateId = Yii::$app->request->post('candidate_id');
        $candidate = Candidates::findOne($candidateId);

        if ($candidate) {
            // Ongeza kura kwa mgombea
            $candidate->votes++;
            $candidate->save();

            // Weka kwamba mpiga kura ameshapiga kura
            $voter->has_voted = 1;
            $voter->save();

            // Hifadhi rekodi ya kura na jina la mgombea
            $record = new VotingRecords([
                'voter_id_number' => $voter->voter_id_number,
                'full_name' => $voter->full_name,
                'candidate_name' => $candidate->name, // ← Ongeza jina la mgombea
                'voted_at' => date('Y-m-d H:i:s'),
            ]);
            $record->save();

            // Tuma salamu za pongezi
            Yii::$app->session->setFlash('success', 'Thank you for voting!');
            return $this->redirect(['site/leo']);
        } else {
            Yii::$app->session->setFlash('error', 'Invalid candidate selected.');
        }
    }

    // 6. Onyesha ukurasa wa kura na wagombea wote
    return $this->render('vote', [
        'candidates' => $candidates,
    ]);
}
}