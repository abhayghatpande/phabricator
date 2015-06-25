<?php

final class PhabricatorMetaMTAMailSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('MetaMTA Mails');
  }

  public function getApplicationClassName() {
    return 'PhabricatorMetaMTAApplication';
  }

  public function newQuery() {
    return new PhabricatorMetaMTAMailQuery();
  }

  protected function shouldShowOrderField() {
    return false;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchUsersField())
      ->setLabel(pht('Actors'))
      ->setKey('actorPHIDs')
      ->setAliases(array('actor', 'actors')),
      id(new PhabricatorSearchUsersField())
      ->setLabel(pht('Recipients'))
      ->setKey('recipientPHIDs')
      ->setAliases(array('recipient', 'recipients')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['actorPHIDs']) {
      $query->withActorPHIDs($map['actorPHIDs']);
    }

    if ($map['recipientPHIDs']) {
      $query->withRecipientPHIDs($map['recipientPHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/mail/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'inbox'  => pht('Inbox'),
      'outbox' => pht('Outbox'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $viewer = $this->requireViewer();

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'inbox':
        return $query->setParameter(
          'recipientPHIDs',
          array($viewer->getPHID()));
      case 'outbox':
        return $query->setParameter(
          'actorPHIDs',
          array($viewer->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $objects,
    PhabricatorSavedQuery $query) {

    $phids = array();
    foreach ($objects as $mail) {
      $phids[] = $mail->getExpandedRecipientPHIDs();
    }
    return array_mergev($phids);
  }

  protected function renderResultList(
    array $mails,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($mails, 'PhabricatorMetaMTAMail');
    $viewer = $this->requireViewer();
    $list = new PHUIObjectItemListView();

    foreach ($mails as $mail) {
      if ($mail->hasSensitiveContent()) {
        $header = pht('< content redacted >');
      } else {
        $header = $mail->getSubject();
      }

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($mail)
        ->setEpoch($mail->getDateCreated())
        ->setObjectName('Mail '.$mail->getID())
        ->setHeader($header)
        ->setHref($this->getURI('detail/'.$mail->getID()));
      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setContent($list);
  }
}
