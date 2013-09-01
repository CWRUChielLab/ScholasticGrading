--
-- User group membership for the ScholasticGrading extension
--
CREATE TABLE /*_*/scholasticgrading_groupuser (
    sggu_group_id int unsigned NOT NULL,
    sggu_user_id int(10) unsigned NOT NULL,
    PRIMARY KEY (sggu_group_id, sggu_user_id),
    FOREIGN KEY (sggu_group_id) REFERENCES /*_*/scholasticgrading_group (sgg_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (sggu_user_id) REFERENCES /*_*/user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/sggu_group ON /*_*/scholasticgrading_groupuser (sggu_group_id);
CREATE INDEX /*i*/sggu_user ON /*_*/scholasticgrading_groupuser (sggu_user_id);
