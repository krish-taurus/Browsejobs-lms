-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access_blocks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_blocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `fee_plan_id` bigint(20) unsigned DEFAULT NULL,
  `level` varchar(255) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lifted_at` timestamp NULL DEFAULT NULL,
  `lifted_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `access_blocks_user_id_foreign` (`user_id`),
  KEY `access_blocks_batch_id_foreign` (`batch_id`),
  KEY `access_blocks_fee_plan_id_foreign` (`fee_plan_id`),
  KEY `access_blocks_tenant_id_index` (`tenant_id`),
  KEY `access_blocks_tenant_id_user_id_lifted_at_index` (`tenant_id`,`user_id`,`lifted_at`),
  CONSTRAINT `access_blocks_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `access_blocks_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `access_blocks_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `access_blocks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activity_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `value` int(11) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_events_user_id_foreign` (`user_id`),
  KEY `activity_events_tenant_id_user_id_type_index` (`tenant_id`,`user_id`,`type`),
  KEY `activity_events_tenant_id_user_id_occurred_at_index` (`tenant_id`,`user_id`,`occurred_at`),
  CONSTRAINT `activity_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `prompt_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `completion_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `total_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_micros` bigint(20) unsigned NOT NULL DEFAULT 0,
  `latency_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'ok',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_events_user_id_foreign` (`user_id`),
  KEY `ai_events_tenant_id_user_id_created_at_index` (`tenant_id`,`user_id`,`created_at`),
  KEY `ai_events_tenant_id_purpose_index` (`tenant_id`,`purpose`),
  CONSTRAINT `ai_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alumni_checkins`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alumni_checkins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `job_application_id` bigint(20) unsigned DEFAULT NULL,
  `milestone` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `scheduled_for` date NOT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `still_employed` tinyint(1) DEFAULT NULL,
  `current_company` varchar(255) DEFAULT NULL,
  `current_ctc_paise` bigint(20) unsigned DEFAULT NULL,
  `would_refer` tinyint(1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `alumni_checkins_user_id_job_application_id_milestone_unique` (`user_id`,`job_application_id`,`milestone`),
  KEY `alumni_checkins_job_application_id_foreign` (`job_application_id`),
  KEY `alumni_checkins_tenant_id_index` (`tenant_id`),
  KEY `alumni_checkins_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `alumni_checkins_job_application_id_foreign` FOREIGN KEY (`job_application_id`) REFERENCES `job_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alumni_checkins_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alumni_checkins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assignment_grades`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_grades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `submission_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `score` int(10) unsigned NOT NULL DEFAULT 0,
  `max_points` int(10) unsigned NOT NULL DEFAULT 100,
  `feedback` text DEFAULT NULL,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`criteria`)),
  `ai_likelihood` tinyint(3) unsigned DEFAULT NULL,
  `ai_event_id` bigint(20) unsigned DEFAULT NULL,
  `graded_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_grades_submission_id_unique` (`submission_id`),
  KEY `assignment_grades_ai_event_id_foreign` (`ai_event_id`),
  KEY `assignment_grades_graded_by_foreign` (`graded_by`),
  KEY `assignment_grades_tenant_id_index` (`tenant_id`),
  KEY `assignment_grades_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `assignment_grades_ai_event_id_foreign` FOREIGN KEY (`ai_event_id`) REFERENCES `ai_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assignment_grades_graded_by_foreign` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assignment_grades_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `assignment_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_grades_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assignment_submissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `body` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_mime` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'submitted',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_submissions_assignment_id_user_id_unique` (`assignment_id`,`user_id`),
  KEY `assignment_submissions_user_id_foreign` (`user_id`),
  KEY `assignment_submissions_tenant_id_index` (`tenant_id`),
  KEY `assignment_submissions_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `assignment_submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_submissions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `instructions` text DEFAULT NULL,
  `rubric` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rubric`)),
  `max_points` int(10) unsigned NOT NULL DEFAULT 100,
  `allow_link` tinyint(1) NOT NULL DEFAULT 1,
  `allow_file` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignments_lesson_id_unique` (`lesson_id`),
  KEY `assignments_tenant_id_index` (`tenant_id`),
  CONSTRAINT `assignments_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `live_session_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `first_joined_at` timestamp NULL DEFAULT NULL,
  `last_left_at` timestamp NULL DEFAULT NULL,
  `open_join_at` timestamp NULL DEFAULT NULL,
  `total_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `attended_pct` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `override_by` bigint(20) unsigned DEFAULT NULL,
  `override_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_live_session_id_user_id_unique` (`live_session_id`,`user_id`),
  KEY `attendance_user_id_foreign` (`user_id`),
  KEY `attendance_override_by_foreign` (`override_by`),
  KEY `attendance_tenant_id_index` (`tenant_id`),
  CONSTRAINT `attendance_live_session_id_foreign` FOREIGN KEY (`live_session_id`) REFERENCES `live_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_override_by_foreign` FOREIGN KEY (`override_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2431 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `target_type` varchar(255) DEFAULT NULL,
  `target_id` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_id_foreign` (`actor_id`),
  KEY `audit_logs_tenant_id_index` (`tenant_id`),
  KEY `audit_logs_action_created_at_index` (`action`,`created_at`),
  CONSTRAINT `audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batch_members`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batch_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'reserved',
  `enrolment_type` varchar(255) NOT NULL DEFAULT 'live',
  `enrolled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_members_batch_id_user_id_unique` (`batch_id`,`user_id`),
  KEY `batch_members_tenant_id_index` (`tenant_id`),
  KEY `batch_members_batch_id_index` (`batch_id`),
  KEY `batch_members_user_id_index` (`user_id`),
  KEY `batch_members_enrolment_type_index` (`enrolment_type`),
  CONSTRAINT `batch_members_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_members_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=244 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batch_mentors`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batch_mentors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_mentors_batch_id_user_id_unique` (`batch_id`,`user_id`),
  KEY `batch_mentors_user_id_foreign` (`user_id`),
  KEY `batch_mentors_tenant_id_batch_id_index` (`tenant_id`,`batch_id`),
  CONSTRAINT `batch_mentors_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_mentors_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_mentors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batch_module_trainers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batch_module_trainers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_module_trainers_batch_id_module_id_unique` (`batch_id`,`module_id`),
  KEY `batch_module_trainers_module_id_foreign` (`module_id`),
  KEY `batch_module_trainers_user_id_foreign` (`user_id`),
  KEY `batch_module_trainers_tenant_id_batch_id_index` (`tenant_id`,`batch_id`),
  CONSTRAINT `batch_module_trainers_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_module_trainers_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_module_trainers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_module_trainers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `number` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `capacity` int(10) unsigned DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `linked_source_batch_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `trainer_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `batches_tenant_id_number_unique` (`tenant_id`,`number`),
  KEY `batches_linked_source_batch_id_foreign` (`linked_source_batch_id`),
  KEY `batches_tenant_id_index` (`tenant_id`),
  KEY `batches_course_id_index` (`course_id`),
  KEY `batches_trainer_id_index` (`trainer_id`),
  CONSTRAINT `batches_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batches_linked_source_batch_id_foreign` FOREIGN KEY (`linked_source_batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `batches_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `canned_responses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canned_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `canned_responses_tenant_id_index` (`tenant_id`),
  KEY `canned_responses_tenant_id_active_index` (`tenant_id`,`active`),
  CONSTRAINT `canned_responses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `career_boosters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `career_boosters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(255) NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `content_source` varchar(255) NOT NULL DEFAULT 'ai',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `career_boosters_user_id_kind_unique` (`user_id`,`kind`),
  KEY `career_boosters_tenant_id_index` (`tenant_id`),
  CONSTRAINT `career_boosters_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `career_boosters_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `celebration_guidances`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `celebration_guidances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `celebration_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `intro` varchar(255) NOT NULL,
  `actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`actions`)),
  `source` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `celebration_guidances_celebration_id_user_id_unique` (`celebration_id`,`user_id`),
  KEY `celebration_guidances_user_id_foreign` (`user_id`),
  KEY `celebration_guidances_tenant_id_index` (`tenant_id`),
  CONSTRAINT `celebration_guidances_celebration_id_foreign` FOREIGN KEY (`celebration_id`) REFERENCES `celebrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `celebration_guidances_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `celebration_guidances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `celebrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `celebrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `display_mode` varchar(255) NOT NULL,
  `anonymous_label` varchar(255) DEFAULT NULL,
  `role_title` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `consented_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `celebrations_student_id_foreign` (`student_id`),
  KEY `celebrations_created_by_foreign` (`created_by`),
  KEY `celebrations_tenant_id_published_at_index` (`tenant_id`,`published_at`),
  CONSTRAINT `celebrations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `celebrations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `celebrations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `code` varchar(255) NOT NULL,
  `number` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `storage_path` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `revoked_at` timestamp NULL DEFAULT NULL,
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificates_code_unique` (`code`),
  UNIQUE KEY `certificates_tenant_id_number_unique` (`tenant_id`,`number`),
  UNIQUE KEY `certificates_tenant_id_user_id_course_id_unique` (`tenant_id`,`user_id`,`course_id`),
  KEY `certificates_user_id_foreign` (`user_id`),
  KEY `certificates_course_id_foreign` (`course_id`),
  KEY `certificates_issued_by_foreign` (`issued_by`),
  KEY `certificates_tenant_id_index` (`tenant_id`),
  CONSTRAINT `certificates_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certificates_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `code_submissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `code_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned DEFAULT NULL,
  `language` varchar(255) NOT NULL,
  `source` longtext NOT NULL,
  `stdin` text DEFAULT NULL,
  `stdout` longtext DEFAULT NULL,
  `stderr` longtext DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `passed_tests` int(10) unsigned NOT NULL DEFAULT 0,
  `total_tests` int(10) unsigned NOT NULL DEFAULT 0,
  `run_time_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `memory_kb` int(10) unsigned NOT NULL DEFAULT 0,
  `kind` varchar(255) NOT NULL DEFAULT 'run',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code_submissions_user_id_foreign` (`user_id`),
  KEY `code_submissions_lesson_id_foreign` (`lesson_id`),
  KEY `code_submissions_tenant_id_user_id_lesson_id_index` (`tenant_id`,`user_id`,`lesson_id`),
  CONSTRAINT `code_submissions_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `code_submissions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `code_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coding_labs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coding_labs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `language` varchar(255) NOT NULL DEFAULT 'python',
  `instructions` text DEFAULT NULL,
  `starter_code` text DEFAULT NULL,
  `test_cases` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`test_cases`)),
  `time_limit_ms` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coding_labs_lesson_id_unique` (`lesson_id`),
  KEY `coding_labs_tenant_id_index` (`tenant_id`),
  CONSTRAINT `coding_labs_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coding_labs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_timeline_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_timeline_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `body` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_timeline_events_lead_id_foreign` (`lead_id`),
  KEY `contact_timeline_events_actor_id_foreign` (`actor_id`),
  KEY `contact_timeline_events_tenant_id_lead_id_occurred_at_index` (`tenant_id`,`lead_id`,`occurred_at`),
  CONSTRAINT `contact_timeline_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contact_timeline_events_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_timeline_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `content_hub_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_hub_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `source` varchar(255) NOT NULL DEFAULT 'manual',
  `published_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `content_hub_items_created_by_foreign` (`created_by`),
  KEY `content_hub_items_tenant_id_published_at_index` (`tenant_id`,`published_at`),
  CONSTRAINT `content_hub_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `content_hub_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversion_nudges`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversion_nudges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `fee_plan_id` bigint(20) unsigned NOT NULL,
  `rung` varchar(255) NOT NULL,
  `channel` varchar(255) NOT NULL DEFAULT 'log',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversion_nudges_fee_plan_id_rung_unique` (`fee_plan_id`,`rung`),
  KEY `conversion_nudges_tenant_id_index` (`tenant_id`),
  CONSTRAINT `conversion_nudges_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversion_nudges_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `course_interview_questions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_interview_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `round_no` tinyint(3) unsigned NOT NULL,
  `round_name` varchar(255) NOT NULL,
  `question` text NOT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_interview_questions_course_id_foreign` (`course_id`),
  KEY `course_iq_lookup` (`tenant_id`,`course_id`,`round_no`,`position`),
  CONSTRAINT `course_interview_questions_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_interview_questions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `program_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'live',
  `fee_paise` bigint(20) unsigned DEFAULT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `masterclass_video_url` varchar(2000) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courses_tenant_id_code_unique` (`tenant_id`,`code`),
  UNIQUE KEY `courses_tenant_id_slug_unique` (`tenant_id`,`slug`),
  KEY `courses_tenant_id_index` (`tenant_id`),
  KEY `courses_program_id_index` (`program_id`),
  KEY `courses_status_index` (`status`),
  CONSTRAINT `courses_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credit_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `feature` varchar(255) NOT NULL,
  `delta` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credit_transactions_user_id_foreign` (`user_id`),
  KEY `credit_transactions_tenant_id_index` (`tenant_id`),
  KEY `credit_transactions_tenant_id_user_id_feature_index` (`tenant_id`,`user_id`,`feature`),
  CONSTRAINT `credit_transactions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credit_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credit_wallets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit_wallets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `feature` varchar(255) NOT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credit_wallets_tenant_id_user_id_feature_unique` (`tenant_id`,`user_id`,`feature`),
  KEY `credit_wallets_user_id_foreign` (`user_id`),
  KEY `credit_wallets_tenant_id_index` (`tenant_id`),
  CONSTRAINT `credit_wallets_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credit_wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_assignment_rules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_assignment_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `mode` varchar(255) NOT NULL DEFAULT 'round_robin',
  `by_course_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`by_course_map`)),
  `round_robin_pointer` bigint(20) unsigned DEFAULT NULL,
  `sla_minutes` smallint(5) unsigned NOT NULL DEFAULT 15,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_assignment_rules_tenant_id_unique` (`tenant_id`),
  KEY `crm_assignment_rules_round_robin_pointer_foreign` (`round_robin_pointer`),
  CONSTRAINT `crm_assignment_rules_round_robin_pointer_foreign` FOREIGN KEY (`round_robin_pointer`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_assignment_rules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_tasks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `source` varchar(255) NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_tasks_lead_id_foreign` (`lead_id`),
  KEY `crm_tasks_assigned_to_foreign` (`assigned_to`),
  KEY `crm_tasks_tenant_id_assigned_to_completed_at_index` (`tenant_id`,`assigned_to`,`completed_at`),
  CONSTRAINT `crm_tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_tasks_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_tasks_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cv_documents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cv_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `version` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'manual',
  `content_source` varchar(10) NOT NULL DEFAULT 'ai',
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `jd_excerpt` text DEFAULT NULL,
  `ats` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ats`)),
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cv_documents_share_token_unique` (`share_token`),
  KEY `cv_documents_tenant_id_index` (`tenant_id`),
  KEY `cv_documents_user_id_index` (`user_id`),
  KEY `cv_documents_course_id_index` (`course_id`),
  KEY `cv_documents_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cv_profiles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cv_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `uploaded_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cv_profiles_user_id_unique` (`user_id`),
  KEY `cv_profiles_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `data_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `reason` varchar(255) DEFAULT NULL,
  `export` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`export`)),
  `processed_by` bigint(20) unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_requests_user_id_foreign` (`user_id`),
  KEY `data_requests_processed_by_foreign` (`processed_by`),
  KEY `data_requests_tenant_id_index` (`tenant_id`),
  KEY `data_requests_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `data_requests_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `data_requests_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `data_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enrolment_pauses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrolment_pauses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `batch_member_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `decided_by` bigint(20) unsigned DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `paused_until` date DEFAULT NULL,
  `resume_batch_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `enrolment_pauses_tenant_id_index` (`tenant_id`),
  KEY `enrolment_pauses_user_id_index` (`user_id`),
  KEY `enrolment_pauses_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entitlements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entitlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `key` varchar(255) NOT NULL,
  `kind` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `source_purchase_id` bigint(20) unsigned DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entitlements_user_id_foreign` (`user_id`),
  KEY `entitlements_source_purchase_id_foreign` (`source_purchase_id`),
  KEY `entitlements_tenant_id_index` (`tenant_id`),
  KEY `entitlements_tenant_id_user_id_key_index` (`tenant_id`,`user_id`,`key`),
  CONSTRAINT `entitlements_source_purchase_id_foreign` FOREIGN KEY (`source_purchase_id`) REFERENCES `product_purchases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `entitlements_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `entitlements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_plans`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `total_paise` bigint(20) unsigned NOT NULL,
  `discount_paise` bigint(20) unsigned NOT NULL DEFAULT 0,
  `gst_rate_bps` smallint(5) unsigned NOT NULL DEFAULT 1800,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fee_plans_user_id_foreign` (`user_id`),
  KEY `fee_plans_batch_id_foreign` (`batch_id`),
  KEY `fee_plans_created_by_foreign` (`created_by`),
  KEY `fee_plans_tenant_id_index` (`tenant_id`),
  KEY `fee_plans_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `fee_plans_tenant_id_batch_id_index` (`tenant_id`,`batch_id`),
  CONSTRAINT `fee_plans_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_plans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_plans_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_plans_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=242 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_reminders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `instalment_id` bigint(20) unsigned NOT NULL,
  `rung` varchar(255) NOT NULL,
  `channel` varchar(255) NOT NULL DEFAULT 'log',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_reminders_instalment_id_rung_unique` (`instalment_id`,`rung`),
  KEY `fee_reminders_tenant_id_index` (`tenant_id`),
  CONSTRAINT `fee_reminders_instalment_id_foreign` FOREIGN KEY (`instalment_id`) REFERENCES `instalments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_reminders_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flashcard_reviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flashcard_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `flashcard_id` bigint(20) unsigned NOT NULL,
  `ease` decimal(4,2) NOT NULL DEFAULT 2.50,
  `interval_days` int(10) unsigned NOT NULL DEFAULT 0,
  `reps` int(10) unsigned NOT NULL DEFAULT 0,
  `lapses` int(10) unsigned NOT NULL DEFAULT 0,
  `due_at` timestamp NULL DEFAULT NULL,
  `last_reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flashcard_reviews_user_id_flashcard_id_unique` (`user_id`,`flashcard_id`),
  KEY `flashcard_reviews_flashcard_id_foreign` (`flashcard_id`),
  KEY `flashcard_reviews_tenant_id_user_id_due_at_index` (`tenant_id`,`user_id`,`due_at`),
  CONSTRAINT `flashcard_reviews_flashcard_id_foreign` FOREIGN KEY (`flashcard_id`) REFERENCES `flashcards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flashcard_reviews_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flashcard_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flashcards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flashcards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `front` text NOT NULL,
  `back` text NOT NULL,
  `source` varchar(255) NOT NULL DEFAULT 'manual',
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flashcards_lesson_id_foreign` (`lesson_id`),
  KEY `flashcards_tenant_id_lesson_id_index` (`tenant_id`,`lesson_id`),
  CONSTRAINT `flashcards_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flashcards_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `funding_news`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `funding_news` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(16) NOT NULL DEFAULT 'funding',
  `company` varchar(120) DEFAULT NULL,
  `headline` varchar(200) DEFAULT NULL,
  `sector` varchar(80) NOT NULL,
  `round` varchar(80) NOT NULL,
  `hub` varchar(80) NOT NULL,
  `hiring_lag_months` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`skills`)),
  `source_name` varchar(120) NOT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `published_on` date NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `funding_news_active_published_on_index` (`active`,`published_on`),
  KEY `funding_news_kind_active_published_on_index` (`kind`,`active`,`published_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hiring_partner_feedback`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hiring_partner_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `job_application_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `company` varchar(255) NOT NULL,
  `role_title` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'requested',
  `ratings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ratings`)),
  `overall` tinyint(3) unsigned DEFAULT NULL,
  `would_hire` tinyint(1) DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `gaps` text DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hiring_partner_feedback_token_unique` (`token`),
  KEY `hiring_partner_feedback_job_application_id_foreign` (`job_application_id`),
  KEY `hiring_partner_feedback_requested_by_foreign` (`requested_by`),
  KEY `hiring_partner_feedback_tenant_id_index` (`tenant_id`),
  KEY `hiring_partner_feedback_course_id_status_index` (`course_id`,`status`),
  CONSTRAINT `hiring_partner_feedback_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hiring_partner_feedback_job_application_id_foreign` FOREIGN KEY (`job_application_id`) REFERENCES `job_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hiring_partner_feedback_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hiring_partner_feedback_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `in_app_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `in_app_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `in_app_notifications_user_id_foreign` (`user_id`),
  KEY `in_app_notifications_tenant_id_user_id_read_at_index` (`tenant_id`,`user_id`,`read_at`),
  CONSTRAINT `in_app_notifications_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `in_app_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `instalments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instalments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `fee_plan_id` bigint(20) unsigned NOT NULL,
  `seq` smallint(5) unsigned NOT NULL,
  `amount_paise` bigint(20) unsigned NOT NULL,
  `due_on` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `razorpay_order_id` varchar(255) DEFAULT NULL,
  `razorpay_payment_link_id` varchar(255) DEFAULT NULL,
  `payment_link_url` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `instalments_fee_plan_id_seq_unique` (`fee_plan_id`,`seq`),
  KEY `instalments_tenant_id_index` (`tenant_id`),
  KEY `instalments_tenant_id_status_due_on_index` (`tenant_id`,`status`,`due_on`),
  KEY `instalments_razorpay_order_id_index` (`razorpay_order_id`),
  KEY `instalments_razorpay_payment_link_id_index` (`razorpay_payment_link_id`),
  CONSTRAINT `instalments_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `instalments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=604 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `intervention_alerts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intervention_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `signals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`signals`)),
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `handled_by` bigint(20) unsigned DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL,
  `resolution` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `intervention_alerts_tenant_id_index` (`tenant_id`),
  KEY `intervention_alerts_user_id_index` (`user_id`),
  KEY `intervention_alerts_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interview_transcripts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_transcripts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `uploader_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(20) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `encrypted_path` varchar(255) DEFAULT NULL,
  `raw_text` longtext DEFAULT NULL,
  `role_title` varchar(255) DEFAULT NULL,
  `outcome` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `parse_error` varchar(500) DEFAULT NULL,
  `questions_found` int(10) unsigned NOT NULL DEFAULT 0,
  `consent_confirmed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `interview_transcripts_tenant_id_index` (`tenant_id`),
  KEY `interview_transcripts_uploader_id_index` (`uploader_id`),
  KEY `interview_transcripts_course_id_index` (`course_id`),
  KEY `interview_transcripts_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_application_rounds`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_application_rounds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `job_application_id` bigint(20) unsigned NOT NULL,
  `round_no` tinyint(3) unsigned NOT NULL,
  `round_type` varchar(40) DEFAULT NULL,
  `happened_on` date NOT NULL,
  `outcome` varchar(20) NOT NULL DEFAULT 'pending',
  `debrief` text DEFAULT NULL,
  `interview_transcript_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_application_rounds_tenant_id_index` (`tenant_id`),
  KEY `job_application_rounds_job_application_id_index` (`job_application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_applications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `job_posting_id` bigint(20) unsigned DEFAULT NULL,
  `job_feed_item_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `cv_document_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'applied',
  `note` varchar(500) DEFAULT NULL,
  `offer_ctc_paise` bigint(20) unsigned DEFAULT NULL,
  `offer_role` varchar(255) DEFAULT NULL,
  `offer_accepted_at` timestamp NULL DEFAULT NULL,
  `placed_at` timestamp NULL DEFAULT NULL,
  `celebrate_consent_at` timestamp NULL DEFAULT NULL,
  `celebrate_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_applications_job_posting_id_user_id_unique` (`job_posting_id`,`user_id`),
  UNIQUE KEY `job_applications_job_feed_item_id_user_id_unique` (`job_feed_item_id`,`user_id`),
  KEY `job_applications_tenant_id_index` (`tenant_id`),
  KEY `job_applications_job_posting_id_index` (`job_posting_id`),
  KEY `job_applications_user_id_index` (`user_id`),
  KEY `job_applications_status_index` (`status`),
  CONSTRAINT `job_applications_job_feed_item_id_foreign` FOREIGN KEY (`job_feed_item_id`) REFERENCES `job_feed_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_feed_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_feed_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `job_feed_source_id` bigint(20) unsigned DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `work_mode` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `apply_url` varchar(255) DEFAULT NULL,
  `source_kind` varchar(255) NOT NULL,
  `role_title` varchar(255) DEFAULT NULL,
  `extracted_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_skills`)),
  `prep_questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`prep_questions`)),
  `seniority` varchar(255) DEFAULT NULL,
  `quality_score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `fingerprint` varchar(64) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `posted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `ingested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_feed_items_tenant_id_fingerprint_unique` (`tenant_id`,`fingerprint`),
  KEY `job_feed_items_job_feed_source_id_foreign` (`job_feed_source_id`),
  KEY `job_feed_items_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `job_feed_items_job_feed_source_id_foreign` FOREIGN KEY (`job_feed_source_id`) REFERENCES `job_feed_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `job_feed_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_feed_saves`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_feed_saves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `job_feed_item_id` bigint(20) unsigned NOT NULL,
  `state` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_feed_saves_user_id_job_feed_item_id_unique` (`user_id`,`job_feed_item_id`),
  KEY `job_feed_saves_job_feed_item_id_foreign` (`job_feed_item_id`),
  KEY `job_feed_saves_tenant_id_index` (`tenant_id`),
  CONSTRAINT `job_feed_saves_job_feed_item_id_foreign` FOREIGN KEY (`job_feed_item_id`) REFERENCES `job_feed_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_feed_saves_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_feed_saves_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_feed_sources`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_feed_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `kind` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `priority` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_feed_sources_tenant_id_index` (`tenant_id`),
  CONSTRAINT `job_feed_sources_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_kit_unlocks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_kit_unlocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `job_feed_item_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_kit_unlocks_user_id_job_feed_item_id_unique` (`user_id`,`job_feed_item_id`),
  KEY `job_kit_unlocks_job_feed_item_id_foreign` (`job_feed_item_id`),
  KEY `job_kit_unlocks_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  CONSTRAINT `job_kit_unlocks_job_feed_item_id_foreign` FOREIGN KEY (`job_feed_item_id`) REFERENCES `job_feed_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_kit_unlocks_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_kit_unlocks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_postings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_postings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `work_mode` varchar(20) DEFAULT NULL,
  `salary_range` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `apply_url` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `closes_on` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_postings_tenant_id_index` (`tenant_id`),
  KEY `job_postings_course_id_index` (`course_id`),
  KEY `job_postings_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=1062 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `knowledge_chunks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_chunks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `ordinal` int(10) unsigned NOT NULL,
  `content` text NOT NULL,
  `token_estimate` int(10) unsigned NOT NULL DEFAULT 0,
  `term_count` int(10) unsigned NOT NULL DEFAULT 0,
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`embedding`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_chunks_document_id_foreign` (`document_id`),
  KEY `knowledge_chunks_tenant_id_index` (`tenant_id`),
  KEY `knowledge_chunks_tenant_id_document_id_ordinal_index` (`tenant_id`,`document_id`,`ordinal`),
  CONSTRAINT `knowledge_chunks_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `knowledge_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `knowledge_chunks_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `knowledge_documents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `source_type` varchar(255) NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `lesson_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `authored_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_documents_course_id_foreign` (`course_id`),
  KEY `knowledge_documents_lesson_id_foreign` (`lesson_id`),
  KEY `knowledge_documents_authored_by_foreign` (`authored_by`),
  KEY `knowledge_documents_tenant_id_index` (`tenant_id`),
  KEY `knowledge_documents_tenant_id_source_type_source_id_index` (`tenant_id`,`source_type`,`source_id`),
  KEY `knowledge_documents_tenant_id_is_active_index` (`tenant_id`,`is_active`),
  KEY `knowledge_documents_tenant_id_source_type_category_index` (`tenant_id`,`source_type`,`category`),
  CONSTRAINT `knowledge_documents_authored_by_foreign` FOREIGN KEY (`authored_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_documents_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_documents_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `knowledge_documents_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_stages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `position` smallint(5) unsigned NOT NULL,
  `is_won` tinyint(1) NOT NULL DEFAULT 0,
  `is_lost` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_stages_tenant_id_slug_unique` (`tenant_id`,`slug`),
  KEY `lead_stages_tenant_id_position_index` (`tenant_id`,`position`),
  CONSTRAINT `lead_stages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lead_type` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `phone_normalized` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `course_slug` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `utm_source` varchar(255) DEFAULT NULL,
  `utm_medium` varchar(255) DEFAULT NULL,
  `utm_campaign` varchar(255) DEFAULT NULL,
  `page` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `consented_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `consent_version` varchar(255) NOT NULL DEFAULT 'v1',
  `crm_synced` tinyint(1) NOT NULL DEFAULT 0,
  `lead_stage_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `score` smallint(5) unsigned NOT NULL DEFAULT 0,
  `first_touch_at` timestamp NULL DEFAULT NULL,
  `sla_due_at` timestamp NULL DEFAULT NULL,
  `sla_breached_at` timestamp NULL DEFAULT NULL,
  `last_replied_at` timestamp NULL DEFAULT NULL,
  `merged_into_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leads_tenant_id_index` (`tenant_id`),
  KEY `leads_lead_type_index` (`lead_type`),
  KEY `leads_created_at_index` (`created_at`),
  KEY `leads_tenant_id_phone_index` (`tenant_id`,`phone`),
  KEY `leads_lead_stage_id_index` (`lead_stage_id`),
  KEY `leads_assigned_to_index` (`assigned_to`),
  KEY `leads_sla_due_at_index` (`sla_due_at`),
  KEY `leads_merged_into_id_index` (`merged_into_id`),
  KEY `leads_tenant_id_phone_normalized_index` (`tenant_id`,`phone_normalized`),
  KEY `leads_last_replied_at_index` (`last_replied_at`),
  CONSTRAINT `leads_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ledger_entries`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ledger_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `fee_plan_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `direction` varchar(255) NOT NULL,
  `amount_paise` bigint(20) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ledger_entries_user_id_foreign` (`user_id`),
  KEY `ledger_entries_payment_id_foreign` (`payment_id`),
  KEY `ledger_entries_tenant_id_index` (`tenant_id`),
  KEY `ledger_entries_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `ledger_entries_fee_plan_id_index` (`fee_plan_id`),
  CONSTRAINT `ledger_entries_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ledger_entries_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_entries_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ledger_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_notes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `recording_id` bigint(20) unsigned DEFAULT NULL,
  `transcript` longtext NOT NULL,
  `notes` longtext DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_uploaded` tinyint(1) NOT NULL DEFAULT 0,
  `source` varchar(255) NOT NULL DEFAULT 'paste',
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `knowledge_document_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lesson_notes_lesson_id_unique` (`lesson_id`),
  KEY `lesson_notes_recording_id_foreign` (`recording_id`),
  KEY `lesson_notes_generated_by_foreign` (`generated_by`),
  KEY `lesson_notes_approved_by_foreign` (`approved_by`),
  KEY `lesson_notes_knowledge_document_id_foreign` (`knowledge_document_id`),
  KEY `lesson_notes_tenant_id_index` (`tenant_id`),
  CONSTRAINT `lesson_notes_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lesson_notes_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lesson_notes_knowledge_document_id_foreign` FOREIGN KEY (`knowledge_document_id`) REFERENCES `knowledge_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lesson_notes_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_notes_recording_id_foreign` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lesson_notes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_videos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_videos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `source` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `url` text DEFAULT NULL,
  `recording_id` bigint(20) unsigned DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lesson_videos_lesson_id_unique` (`lesson_id`),
  KEY `lesson_videos_recording_id_foreign` (`recording_id`),
  KEY `lesson_videos_tenant_id_index` (`tenant_id`),
  CONSTRAINT `lesson_videos_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_videos_recording_id_foreign` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lesson_videos_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lessons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lessons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lessons_tenant_id_index` (`tenant_id`),
  KEY `lessons_topic_id_index` (`topic_id`),
  CONSTRAINT `lessons_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lessons_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `live_sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned DEFAULT NULL,
  `kind` varchar(20) NOT NULL DEFAULT 'class',
  `host_user_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `scheduled_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_end` timestamp NULL DEFAULT NULL,
  `zoom_meeting_id` varchar(255) DEFAULT NULL,
  `zoom_license_id` bigint(20) unsigned DEFAULT NULL,
  `zoom_join_url` text DEFAULT NULL,
  `zoom_start_url` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `auto_record` tinyint(1) NOT NULL DEFAULT 1,
  `reminder_token` varchar(255) DEFAULT NULL,
  `wrapped_up_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `live_sessions_topic_id_foreign` (`topic_id`),
  KEY `live_sessions_tenant_id_index` (`tenant_id`),
  KEY `live_sessions_batch_id_index` (`batch_id`),
  KEY `live_sessions_zoom_meeting_id_index` (`zoom_meeting_id`),
  KEY `live_sessions_host_user_id_foreign` (`host_user_id`),
  KEY `live_sessions_zoom_license_id_foreign` (`zoom_license_id`),
  CONSTRAINT `live_sessions_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_sessions_host_user_id_foreign` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `live_sessions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `live_sessions_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `live_sessions_zoom_license_id_foreign` FOREIGN KEY (`zoom_license_id`) REFERENCES `zoom_licenses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=249 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `magic_links`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `magic_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `magic_links_token_hash_unique` (`token_hash`),
  KEY `magic_links_user_id_foreign` (`user_id`),
  KEY `magic_links_tenant_id_index` (`tenant_id`),
  CONSTRAINT `magic_links_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `magic_links_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `market_jds`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `market_jds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(255) NOT NULL DEFAULT 'manual',
  `external_ref` varchar(255) DEFAULT NULL,
  `role_title` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `seniority` varchar(255) DEFAULT NULL,
  `raw_jd` text NOT NULL,
  `fingerprint` varchar(64) NOT NULL,
  `extracted_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_skills`)),
  `quality_score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `parse_status` varchar(255) NOT NULL DEFAULT 'pending',
  `parsed_at` timestamp NULL DEFAULT NULL,
  `ingested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `market_jds_tenant_id_fingerprint_unique` (`tenant_id`,`fingerprint`),
  KEY `market_jds_tenant_id_index` (`tenant_id`),
  KEY `market_jds_course_id_index` (`course_id`),
  KEY `market_jds_tenant_id_role_title_index` (`tenant_id`,`role_title`),
  CONSTRAINT `market_jds_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `market_jds_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `market_signals`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `market_signals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(32) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `effective_on` date NOT NULL,
  `source` varchar(64) NOT NULL DEFAULT 'seed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `market_signals_kind_effective_on_unique` (`kind`,`effective_on`),
  KEY `market_signals_effective_on_index` (`effective_on`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mentor_availabilities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mentor_availabilities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `mentor_profile_id` bigint(20) unsigned NOT NULL,
  `weekday` tinyint(3) unsigned NOT NULL,
  `start_minute` smallint(5) unsigned NOT NULL,
  `end_minute` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mentor_availabilities_tenant_id_index` (`tenant_id`),
  KEY `mentor_availabilities_mentor_profile_id_index` (`mentor_profile_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mentor_availability_exceptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mentor_availability_exceptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `mentor_profile_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `start_minute` smallint(5) unsigned DEFAULT NULL,
  `end_minute` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mentor_availability_exceptions_tenant_id_index` (`tenant_id`),
  KEY `mentor_availability_exceptions_mentor_profile_id_index` (`mentor_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mentor_profiles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mentor_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `expertise_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`expertise_tags`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `google_calendar_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `course_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`course_ids`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `mentor_profiles_tenant_id_user_id_unique` (`tenant_id`,`user_id`),
  KEY `mentor_profiles_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mentor_sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mentor_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `mentor_profile_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `purpose` varchar(30) NOT NULL DEFAULT 'mentoring',
  `status` varchar(20) NOT NULL DEFAULT 'booked',
  `no_show_side` varchar(10) DEFAULT NULL,
  `starts_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `zoom_meeting_id` varchar(255) DEFAULT NULL,
  `join_url` varchar(500) DEFAULT NULL,
  `start_url` varchar(1000) DEFAULT NULL,
  `feedback` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`feedback`)),
  `feedback_score` tinyint(3) unsigned DEFAULT NULL,
  `student_rating` tinyint(3) unsigned DEFAULT NULL,
  `student_comment` varchar(500) DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `reminded_24h_at` timestamp NULL DEFAULT NULL,
  `reminded_1h_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mentor_sessions_mentor_profile_id_starts_at_index` (`mentor_profile_id`,`starts_at`),
  KEY `mentor_sessions_tenant_id_index` (`tenant_id`),
  KEY `mentor_sessions_mentor_profile_id_index` (`mentor_profile_id`),
  KEY `mentor_sessions_student_id_index` (`student_id`),
  KEY `mentor_sessions_status_index` (`status`),
  KEY `mentor_sessions_starts_at_index` (`starts_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `message_preferences`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `preferred_channel` varchar(255) NOT NULL DEFAULT 'whatsapp',
  `marketing_opt_in` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_preferences_user_id_unique` (`user_id`),
  KEY `message_preferences_tenant_id_index` (`tenant_id`),
  CONSTRAINT `message_preferences_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `message_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `key` varchar(255) NOT NULL,
  `channel` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'utility',
  `name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `locale` varchar(8) NOT NULL DEFAULT 'en',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_templates_tenant_id_key_channel_unique` (`tenant_id`,`key`,`channel`),
  KEY `message_templates_tenant_id_index` (`tenant_id`),
  CONSTRAINT `message_templates_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `direction` varchar(255) NOT NULL DEFAULT 'out',
  `channel` varchar(255) NOT NULL,
  `template_key` varchar(255) DEFAULT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'utility',
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'queued',
  `suppressed_reason` varchar(255) DEFAULT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `failed_reason` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_user_id_foreign` (`user_id`),
  KEY `messages_lead_id_foreign` (`lead_id`),
  KEY `messages_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `messages_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `messages_provider_message_id_index` (`provider_message_id`),
  CONSTRAINT `messages_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=375 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mock_blueprints`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mock_blueprints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `job_feed_item_id` bigint(20) unsigned DEFAULT NULL,
  `role_title` varchar(255) NOT NULL,
  `skill` varchar(255) DEFAULT NULL,
  `competencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`competencies`)),
  `opening_question` varchar(500) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mock_blueprints_course_id_foreign` (`course_id`),
  KEY `mock_blueprints_tenant_id_course_id_index` (`tenant_id`,`course_id`),
  KEY `mock_blueprints_job_feed_item_id_foreign` (`job_feed_item_id`),
  CONSTRAINT `mock_blueprints_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mock_blueprints_job_feed_item_id_foreign` FOREIGN KEY (`job_feed_item_id`) REFERENCES `job_feed_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mock_blueprints_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mock_interviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mock_interviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `mock_blueprint_id` bigint(20) unsigned NOT NULL,
  `mode` varchar(255) NOT NULL DEFAULT 'text',
  `is_room` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'in_progress',
  `overall_score` tinyint(3) unsigned DEFAULT NULL,
  `scorecard` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scorecard`)),
  `scorecard_source` varchar(255) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provider_session_id` varchar(255) DEFAULT NULL,
  `join_url` varchar(500) DEFAULT NULL,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `cost_micros` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mock_interviews_user_id_foreign` (`user_id`),
  KEY `mock_interviews_mock_blueprint_id_foreign` (`mock_blueprint_id`),
  KEY `mock_interviews_tenant_id_user_id_status_index` (`tenant_id`,`user_id`,`status`),
  KEY `mock_interviews_provider_session_id_index` (`provider_session_id`),
  CONSTRAINT `mock_interviews_mock_blueprint_id_foreign` FOREIGN KEY (`mock_blueprint_id`) REFERENCES `mock_blueprints` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mock_interviews_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mock_interviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mock_turns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mock_turns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `mock_interview_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mock_turns_tenant_id_foreign` (`tenant_id`),
  KEY `mock_turns_mock_interview_id_id_index` (`mock_interview_id`,`id`),
  CONSTRAINT `mock_turns_mock_interview_id_foreign` FOREIGN KEY (`mock_interview_id`) REFERENCES `mock_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mock_turns_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `module_mock_requirements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_mock_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `required` tinyint(3) unsigned NOT NULL,
  `completed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `unlocked_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cleared_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_mock_requirements_user_id_module_id_unique` (`user_id`,`module_id`),
  KEY `module_mock_requirements_module_id_foreign` (`module_id`),
  KEY `module_mock_requirements_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  CONSTRAINT `module_mock_requirements_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_mock_requirements_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_mock_requirements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `required_mocks` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modules_tenant_id_index` (`tenant_id`),
  KEY `modules_course_id_index` (`course_id`),
  CONSTRAINT `modules_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monetization_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monetization_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `cv_free_grants` int(10) unsigned NOT NULL DEFAULT 3,
  `voice_included_live` int(10) unsigned NOT NULL DEFAULT 5,
  `voice_included_self_paced` int(10) unsigned NOT NULL DEFAULT 2,
  `self_paced_pct_bps` int(10) unsigned NOT NULL DEFAULT 5000,
  `text_practice_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monetization_settings_tenant_id_index` (`tenant_id`),
  CONSTRAINT `monetization_settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nps_pulses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nps_pulses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `milestone` varchar(20) NOT NULL,
  `score` tinyint(3) unsigned DEFAULT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `routed` varchar(20) DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nps_pulses_user_id_milestone_unique` (`user_id`,`milestone`),
  KEY `nps_pulses_tenant_id_index` (`tenant_id`),
  KEY `nps_pulses_user_id_index` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `onboarding_checklists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `onboarding_checklists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `welcome_call_at` timestamp NULL DEFAULT NULL,
  `platform_tour_at` timestamp NULL DEFAULT NULL,
  `week1_checkin_at` timestamp NULL DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onboarding_checklists_user_id_unique` (`user_id`),
  KEY `onboarding_checklists_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `otp_codes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `channel` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `otp_codes_tenant_id_index` (`tenant_id`),
  KEY `otp_codes_tenant_id_identifier_purpose_index` (`tenant_id`,`identifier`,`purpose`),
  CONSTRAINT `otp_codes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `fee_plan_id` bigint(20) unsigned NOT NULL,
  `instalment_id` bigint(20) unsigned NOT NULL,
  `razorpay_order_id` varchar(255) DEFAULT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `amount_paise` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'created',
  `method` varchar(255) DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `raw` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_razorpay_payment_id_unique` (`razorpay_payment_id`),
  KEY `payments_fee_plan_id_foreign` (`fee_plan_id`),
  KEY `payments_instalment_id_foreign` (`instalment_id`),
  KEY `payments_tenant_id_index` (`tenant_id`),
  KEY `payments_razorpay_order_id_index` (`razorpay_order_id`),
  KEY `payments_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `payments_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_instalment_id_foreign` FOREIGN KEY (`instalment_id`) REFERENCES `instalments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=363 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permission_role`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_role` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `placement_stories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `placement_stories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `before_label` varchar(255) NOT NULL,
  `after_role` varchar(255) NOT NULL,
  `package_label` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_color` varchar(9) DEFAULT NULL,
  `rounds` tinyint(3) unsigned DEFAULT NULL,
  `quote` text DEFAULT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `is_sample` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `placement_stories_course_id_foreign` (`course_id`),
  KEY `placement_stories_lookup` (`tenant_id`,`course_id`,`is_published`,`position`),
  CONSTRAINT `placement_stories_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `placement_stories_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `platform_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_settings_group_key_unique` (`group`,`key`),
  KEY `platform_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `platform_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `points_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `points_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(255) NOT NULL,
  `source_key` varchar(255) NOT NULL,
  `points` int(10) unsigned NOT NULL,
  `awarded_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `points_events_once` (`tenant_id`,`user_id`,`source`,`source_key`),
  KEY `points_events_user_id_foreign` (`user_id`),
  KEY `points_events_batch_id_foreign` (`batch_id`),
  KEY `points_events_tenant_id_batch_id_awarded_on_index` (`tenant_id`,`batch_id`,`awarded_on`),
  KEY `points_events_tenant_id_user_id_awarded_on_index` (`tenant_id`,`user_id`,`awarded_on`),
  CONSTRAINT `points_events_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `points_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `points_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `points_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `points_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `attendance_points` int(10) unsigned NOT NULL DEFAULT 20,
  `punctuality_points` int(10) unsigned NOT NULL DEFAULT 5,
  `quiz_points` int(10) unsigned NOT NULL DEFAULT 30,
  `lab_points` int(10) unsigned NOT NULL DEFAULT 25,
  `streak_day_points` int(10) unsigned NOT NULL DEFAULT 10,
  `mock_points` int(10) unsigned NOT NULL DEFAULT 40,
  `daily_cap` int(10) unsigned NOT NULL DEFAULT 150,
  `attendance_min_pct` tinyint(3) unsigned NOT NULL DEFAULT 60,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `points_settings_tenant_id_unique` (`tenant_id`),
  CONSTRAINT `points_settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pri_calibrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pri_calibrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `weights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`weights`)),
  `correlations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`correlations`)),
  `cohort_size` int(10) unsigned NOT NULL,
  `applied` tinyint(1) NOT NULL DEFAULT 0,
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pri_calibrations_tenant_id_applied_computed_at_index` (`tenant_id`,`applied`,`computed_at`),
  CONSTRAINT `pri_calibrations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `product_purchases`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `subscription_id` bigint(20) unsigned DEFAULT NULL,
  `sku` varchar(255) NOT NULL,
  `feature` varchar(255) DEFAULT NULL,
  `kind` varchar(255) NOT NULL,
  `amount_paise` bigint(20) unsigned NOT NULL,
  `taxable_paise` bigint(20) unsigned NOT NULL DEFAULT 0,
  `cgst_paise` bigint(20) unsigned NOT NULL DEFAULT 0,
  `sgst_paise` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_paise` bigint(20) unsigned NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `razorpay_order_id` varchar(255) DEFAULT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `receipt_number` varchar(255) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `refund_reason` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_purchases_user_id_foreign` (`user_id`),
  KEY `product_purchases_product_id_foreign` (`product_id`),
  KEY `product_purchases_subscription_id_foreign` (`subscription_id`),
  KEY `product_purchases_tenant_id_index` (`tenant_id`),
  KEY `product_purchases_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `product_purchases_razorpay_order_id_index` (`razorpay_order_id`),
  KEY `product_purchases_tenant_id_status_feature_index` (`tenant_id`,`status`,`feature`),
  CONSTRAINT `product_purchases_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_purchases_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_purchases_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_purchases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `feature` varchar(255) DEFAULT NULL,
  `kind` varchar(255) NOT NULL,
  `price_paise` bigint(20) unsigned NOT NULL,
  `grant_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `period_days` int(10) unsigned DEFAULT NULL,
  `source_batch_id` bigint(20) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_tenant_id_sku_unique` (`tenant_id`,`sku`),
  KEY `products_source_batch_id_foreign` (`source_batch_id`),
  KEY `products_tenant_id_index` (`tenant_id`),
  CONSTRAINT `products_source_batch_id_foreign` FOREIGN KEY (`source_batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `programs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programs_tenant_id_slug_unique` (`tenant_id`,`slug`),
  KEY `programs_tenant_id_index` (`tenant_id`),
  CONSTRAINT `programs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_specs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_specs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `tech_stack` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tech_stack`)),
  `architecture` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`architecture`)),
  `architecture_source` varchar(255) NOT NULL DEFAULT 'none',
  `architecture_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_specs_lesson_id_unique` (`lesson_id`),
  KEY `project_specs_tenant_id_index` (`tenant_id`),
  CONSTRAINT `project_specs_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_specs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pulse_digests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pulse_digests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `digest_date` date NOT NULL,
  `narrative` text NOT NULL,
  `item_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`item_ids`)),
  `source` varchar(255) NOT NULL,
  `ai_event_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_digests_tenant_id_digest_date_unique` (`tenant_id`,`digest_date`),
  KEY `pulse_digests_ai_event_id_foreign` (`ai_event_id`),
  CONSTRAINT `pulse_digests_ai_event_id_foreign` FOREIGN KEY (`ai_event_id`) REFERENCES `ai_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pulse_digests_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pulse_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pulse_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `source_name` varchar(255) NOT NULL,
  `course_slug` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `published_on` date NOT NULL,
  `added_by` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pulse_items_added_by_foreign` (`added_by`),
  KEY `pulse_items_tenant_id_published_on_index` (`tenant_id`,`published_on`),
  CONSTRAINT `pulse_items_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pulse_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quiz_attempts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `quiz_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `deadline_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `score_pct` tinyint(3) unsigned DEFAULT NULL,
  `correct_count` int(10) unsigned DEFAULT NULL,
  `total_count` int(10) unsigned DEFAULT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  `integrity` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`integrity`)),
  `reminded_at` timestamp NULL DEFAULT NULL,
  `flagged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quiz_attempts_quiz_id_user_id_unique` (`quiz_id`,`user_id`),
  KEY `quiz_attempts_user_id_foreign` (`user_id`),
  KEY `quiz_attempts_tenant_id_index` (`tenant_id`),
  KEY `quiz_attempts_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  CONSTRAINT `quiz_attempts_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quiz_questions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `quiz_id` bigint(20) unsigned NOT NULL,
  `prompt` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options`)),
  `correct_index` tinyint(3) unsigned NOT NULL,
  `explanation` text DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quiz_questions_tenant_id_index` (`tenant_id`),
  KEY `quiz_questions_quiz_id_position_index` (`quiz_id`,`position`),
  CONSTRAINT `quiz_questions_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_questions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quizzes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quizzes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `instructions` text DEFAULT NULL,
  `time_limit_sec` int(10) unsigned NOT NULL DEFAULT 600,
  `pass_pct` tinyint(3) unsigned NOT NULL DEFAULT 60,
  `shuffle` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `source` varchar(255) NOT NULL DEFAULT 'manual',
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_uploaded` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quizzes_lesson_id_unique` (`lesson_id`),
  KEY `quizzes_approved_by_foreign` (`approved_by`),
  KEY `quizzes_tenant_id_index` (`tenant_id`),
  KEY `quizzes_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `quizzes_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quizzes_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quizzes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quota_usages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quota_usages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `feature` varchar(255) NOT NULL,
  `period_key` varchar(255) NOT NULL,
  `used` int(10) unsigned NOT NULL DEFAULT 0,
  `limit_amount` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quota_usages_tenant_id_user_id_feature_period_key_unique` (`tenant_id`,`user_id`,`feature`,`period_key`),
  KEY `quota_usages_user_id_foreign` (`user_id`),
  KEY `quota_usages_tenant_id_index` (`tenant_id`),
  CONSTRAINT `quota_usages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quota_usages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `real_interview_questions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `real_interview_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `interview_transcript_id` bigint(20) unsigned DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `role_title` varchar(255) NOT NULL,
  `question` text NOT NULL,
  `normalized_question` text NOT NULL,
  `fingerprint` varchar(64) NOT NULL,
  `topic_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`topic_tags`)),
  `difficulty` varchar(20) DEFAULT NULL,
  `round_type` varchar(40) DEFAULT NULL,
  `follow_ups` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`follow_ups`)),
  `strong_answer` text DEFAULT NULL,
  `struggle_points` text DEFAULT NULL,
  `outcome` varchar(40) DEFAULT NULL,
  `confidence` varchar(10) NOT NULL DEFAULT 'medium',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `asked_count` int(10) unsigned NOT NULL DEFAULT 1,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `real_interview_questions_tenant_id_fingerprint_unique` (`tenant_id`,`fingerprint`),
  KEY `real_interview_questions_tenant_id_index` (`tenant_id`),
  KEY `real_interview_questions_interview_transcript_id_index` (`interview_transcript_id`),
  KEY `real_interview_questions_course_id_index` (`course_id`),
  KEY `real_interview_questions_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receipts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `fee_plan_id` bigint(20) unsigned NOT NULL,
  `number` varchar(255) NOT NULL,
  `taxable_paise` bigint(20) unsigned NOT NULL,
  `cgst_paise` bigint(20) unsigned NOT NULL,
  `sgst_paise` bigint(20) unsigned NOT NULL,
  `total_paise` bigint(20) unsigned NOT NULL,
  `storage_path` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipts_tenant_id_number_unique` (`tenant_id`,`number`),
  KEY `receipts_payment_id_foreign` (`payment_id`),
  KEY `receipts_fee_plan_id_foreign` (`fee_plan_id`),
  KEY `receipts_tenant_id_index` (`tenant_id`),
  CONSTRAINT `receipts_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `receipts_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `receipts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recordings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recordings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `live_session_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `storage_path` varchar(255) DEFAULT NULL,
  `play_url` text DEFAULT NULL,
  `passcode` varchar(255) DEFAULT NULL,
  `size_bytes` bigint(20) unsigned DEFAULT NULL,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recordings_topic_id_foreign` (`topic_id`),
  KEY `recordings_tenant_id_index` (`tenant_id`),
  KEY `recordings_live_session_id_index` (`live_session_id`),
  CONSTRAINT `recordings_live_session_id_foreign` FOREIGN KEY (`live_session_id`) REFERENCES `live_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recordings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recordings_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `narrative` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `ai_event_id` bigint(20) unsigned DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reports_recipient_id_type_period_start_unique` (`recipient_id`,`type`,`period_start`),
  KEY `reports_ai_event_id_foreign` (`ai_event_id`),
  KEY `reports_tenant_id_recipient_id_type_created_at_index` (`tenant_id`,`recipient_id`,`type`,`created_at`),
  CONSTRAINT `reports_ai_event_id_foreign` FOREIGN KEY (`ai_event_id`) REFERENCES `ai_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_recipient_id_foreign` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `review_intake`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_intake` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `drive_file_id` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `published_kind` varchar(255) DEFAULT NULL,
  `published_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_intake_tenant_id_drive_file_id_unique` (`tenant_id`,`drive_file_id`),
  KEY `review_intake_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `review_intake_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `author_meta` varchar(255) DEFAULT NULL,
  `course_slug` varchar(255) DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `body` text NOT NULL,
  `source` varchar(255) NOT NULL,
  `reviewed_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviews_tenant_id_index` (`tenant_id`),
  KEY `reviews_tenant_id_source_index` (`tenant_id`,`source`),
  KEY `reviews_tenant_id_is_active_sort_order_index` (`tenant_id`,`is_active`,`sort_order`),
  CONSTRAINT `reviews_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_user`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_user` (
  `role_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`user_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salary_benchmarks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_benchmarks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `role_title` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `experience_band` varchar(255) NOT NULL,
  `p25_ctc_paise` bigint(20) unsigned NOT NULL,
  `p50_ctc_paise` bigint(20) unsigned NOT NULL,
  `p75_ctc_paise` bigint(20) unsigned NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `effective_on` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_benchmarks_course_id_foreign` (`course_id`),
  KEY `salary_benchmarks_tenant_id_index` (`tenant_id`),
  KEY `salary_benchmarks_tenant_id_role_title_city_index` (`tenant_id`,`role_title`,`city`),
  CONSTRAINT `salary_benchmarks_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_benchmarks_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `score_snapshots`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `score_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `captured_on` date NOT NULL,
  `pri` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `engagement` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `risk_dropout` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `risk_placement` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `score_snapshots_tenant_id_user_id_captured_on_unique` (`tenant_id`,`user_id`,`captured_on`),
  KEY `score_snapshots_user_id_foreign` (`user_id`),
  KEY `score_snapshots_tenant_id_index` (`tenant_id`),
  CONSTRAINT `score_snapshots_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `score_snapshots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `session_changes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `session_changes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `live_session_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `old_start` timestamp NULL DEFAULT NULL,
  `new_start` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_changes_actor_id_foreign` (`actor_id`),
  KEY `session_changes_tenant_id_index` (`tenant_id`),
  KEY `session_changes_live_session_id_index` (`live_session_id`),
  CONSTRAINT `session_changes_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `session_changes_live_session_id_foreign` FOREIGN KEY (`live_session_id`) REFERENCES `live_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `session_changes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_contents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `key` varchar(60) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_contents_tenant_id_key_unique` (`tenant_id`,`key`),
  CONSTRAINT `site_contents_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_profiles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_profiles_user_id_unique` (`user_id`),
  KEY `staff_profiles_tenant_id_index` (`tenant_id`),
  CONSTRAINT `staff_profiles_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_badges`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_badges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `badge` varchar(255) NOT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_badges_user_id_badge_unique` (`user_id`,`badge`),
  KEY `student_badges_tenant_id_index` (`tenant_id`),
  CONSTRAINT `student_badges_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_badges_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_scores`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `engagement` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `risk_dropout` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `risk_placement` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `pri` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `streak_days` int(10) unsigned NOT NULL DEFAULT 0,
  `mastery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mastery`)),
  `next_action` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`next_action`)),
  `red_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`red_flags`)),
  `computed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_scores_tenant_id_user_id_unique` (`tenant_id`,`user_id`),
  KEY `student_scores_user_id_foreign` (`user_id`),
  KEY `student_scores_tenant_id_index` (`tenant_id`),
  KEY `student_scores_tenant_id_risk_dropout_index` (`tenant_id`,`risk_dropout`),
  CONSTRAINT `student_scores_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_scores_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `razorpay_subscription_id` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `current_period_end` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_user_id_foreign` (`user_id`),
  KEY `subscriptions_product_id_foreign` (`product_id`),
  KEY `subscriptions_tenant_id_index` (`tenant_id`),
  KEY `subscriptions_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `subscriptions_razorpay_subscription_id_index` (`razorpay_subscription_id`),
  CONSTRAINT `subscriptions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscriptions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `support_team_user`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_team_user` (
  `support_team_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`support_team_id`,`user_id`),
  KEY `support_team_user_user_id_foreign` (`user_id`),
  CONSTRAINT `support_team_user_support_team_id_foreign` FOREIGN KEY (`support_team_id`) REFERENCES `support_teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `support_teams`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_teams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_teams_tenant_id_slug_unique` (`tenant_id`,`slug`),
  KEY `support_teams_tenant_id_index` (`tenant_id`),
  CONSTRAINT `support_teams_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syllabus_recommendations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syllabus_recommendations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `source` varchar(255) NOT NULL DEFAULT 'on_demand',
  `content_source` varchar(255) NOT NULL DEFAULT 'ai',
  `summary` text DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`evidence`)),
  `market_sample` smallint(5) unsigned NOT NULL DEFAULT 0,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `syllabus_recommendations_generated_by_foreign` (`generated_by`),
  KEY `syllabus_recommendations_reviewed_by_foreign` (`reviewed_by`),
  KEY `syllabus_recommendations_tenant_id_index` (`tenant_id`),
  KEY `syllabus_recommendations_course_id_status_index` (`course_id`,`status`),
  CONSTRAINT `syllabus_recommendations_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_recommendations_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `syllabus_recommendations_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `syllabus_recommendations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `syllabuses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `syllabuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `curriculum_hash` varchar(64) DEFAULT NULL,
  `is_stale` tinyint(1) NOT NULL DEFAULT 0,
  `render_status` varchar(255) NOT NULL DEFAULT 'pending',
  `storage_path` varchar(255) DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `syllabuses_course_id_unique` (`course_id`),
  KEY `syllabuses_generated_by_foreign` (`generated_by`),
  KEY `syllabuses_approved_by_foreign` (`approved_by`),
  KEY `syllabuses_tenant_id_index` (`tenant_id`),
  CONSTRAINT `syllabuses_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `syllabuses_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabuses_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `syllabuses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenants`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `branding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`branding`)),
  `feature_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`feature_flags`)),
  `plan` varchar(255) NOT NULL DEFAULT 'standard',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `batch_numbering_pattern` varchar(255) NOT NULL DEFAULT '{COURSE}-{YYYYMM}-{seq}',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  UNIQUE KEY `tenants_domain_unique` (`domain`),
  KEY `tenants_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testimonials`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testimonials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `batch_id` bigint(20) unsigned DEFAULT NULL,
  `course_slug` varchar(255) DEFAULT NULL,
  `stage` varchar(255) DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `body` text NOT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `consent_publish` tinyint(1) NOT NULL DEFAULT 0,
  `followed_instagram` tinyint(1) NOT NULL DEFAULT 0,
  `subscribed_youtube` tinyint(1) NOT NULL DEFAULT 0,
  `posted_google_review` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `review_id` bigint(20) unsigned DEFAULT NULL,
  `voucher_issue_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `testimonials_user_id_foreign` (`user_id`),
  KEY `testimonials_batch_id_foreign` (`batch_id`),
  KEY `testimonials_review_id_foreign` (`review_id`),
  KEY `testimonials_reviewed_by_foreign` (`reviewed_by`),
  KEY `testimonials_tenant_id_index` (`tenant_id`),
  KEY `testimonials_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `testimonials_tenant_id_user_id_batch_id_index` (`tenant_id`,`user_id`,`batch_id`),
  KEY `testimonials_voucher_issue_id_index` (`voucher_issue_id`),
  CONSTRAINT `testimonials_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `testimonials_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE SET NULL,
  CONSTRAINT `testimonials_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `testimonials_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `testimonials_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_attachments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `ticket_message_id` bigint(20) unsigned DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime` varchar(255) DEFAULT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_attachments_ticket_message_id_foreign` (`ticket_message_id`),
  KEY `ticket_attachments_tenant_id_index` (`tenant_id`),
  KEY `ticket_attachments_tenant_id_ticket_id_index` (`tenant_id`,`ticket_id`),
  CONSTRAINT `ticket_attachments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_attachments_ticket_message_id_foreign` FOREIGN KEY (`ticket_message_id`) REFERENCES `ticket_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_deflections`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_deflections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `category` varchar(255) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `confidence` varchar(255) NOT NULL,
  `cited_chunk_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cited_chunk_ids`)),
  `outcome` varchar(255) NOT NULL DEFAULT 'offered',
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `ai_event_id` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_deflections_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_deflections_ai_event_id_foreign` (`ai_event_id`),
  KEY `ticket_deflections_tenant_id_index` (`tenant_id`),
  KEY `ticket_deflections_tenant_id_outcome_index` (`tenant_id`,`outcome`),
  KEY `ticket_deflections_tenant_id_category_outcome_index` (`tenant_id`,`category`,`outcome`),
  KEY `ticket_deflections_student_id_created_at_index` (`student_id`,`created_at`),
  CONSTRAINT `ticket_deflections_ai_event_id_foreign` FOREIGN KEY (`ai_event_id`) REFERENCES `ai_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_deflections_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_deflections_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_deflections_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `author_type` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `channel` varchar(255) NOT NULL DEFAULT 'portal',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_messages_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_messages_author_id_foreign` (`author_id`),
  KEY `ticket_messages_tenant_id_index` (`tenant_id`),
  KEY `ticket_messages_tenant_id_ticket_id_index` (`tenant_id`,`ticket_id`),
  CONSTRAINT `ticket_messages_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_messages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_messages_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_routes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_routes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `category` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `routing` varchar(255) NOT NULL,
  `support_team_id` bigint(20) unsigned DEFAULT NULL,
  `first_response_minutes` int(10) unsigned NOT NULL,
  `resolution_minutes` int(10) unsigned NOT NULL,
  `round_robin_pointer` bigint(20) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_routes_tenant_id_category_unique` (`tenant_id`,`category`),
  KEY `ticket_routes_support_team_id_foreign` (`support_team_id`),
  KEY `ticket_routes_tenant_id_index` (`tenant_id`),
  CONSTRAINT `ticket_routes_support_team_id_foreign` FOREIGN KEY (`support_team_id`) REFERENCES `support_teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_routes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `reference` varchar(255) NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(255) NOT NULL,
  `ai_category` varchar(255) DEFAULT NULL,
  `priority` varchar(255) NOT NULL DEFAULT 'normal',
  `ai_urgency` varchar(255) DEFAULT NULL,
  `ai_sentiment` varchar(255) DEFAULT NULL,
  `ai_priority_raised` tinyint(1) NOT NULL DEFAULT 0,
  `ai_duplicate_of_id` bigint(20) unsigned DEFAULT NULL,
  `ai_triaged_at` timestamp NULL DEFAULT NULL,
  `ai_event_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `assignee_id` bigint(20) unsigned DEFAULT NULL,
  `support_team_id` bigint(20) unsigned DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `first_response_due_at` timestamp NULL DEFAULT NULL,
  `resolution_due_at` timestamp NULL DEFAULT NULL,
  `first_response_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `reopened_at` timestamp NULL DEFAULT NULL,
  `response_warned_at` timestamp NULL DEFAULT NULL,
  `breached_at` timestamp NULL DEFAULT NULL,
  `resolution_breached_at` timestamp NULL DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `escalated_to_id` bigint(20) unsigned DEFAULT NULL,
  `csat_rating` tinyint(3) unsigned DEFAULT NULL,
  `csat_comment` varchar(255) DEFAULT NULL,
  `csat_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickets_student_id_foreign` (`student_id`),
  KEY `tickets_lead_id_foreign` (`lead_id`),
  KEY `tickets_assignee_id_foreign` (`assignee_id`),
  KEY `tickets_support_team_id_foreign` (`support_team_id`),
  KEY `tickets_escalated_to_id_foreign` (`escalated_to_id`),
  KEY `tickets_tenant_id_index` (`tenant_id`),
  KEY `tickets_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `tickets_tenant_id_assignee_id_status_index` (`tenant_id`,`assignee_id`,`status`),
  KEY `tickets_tenant_id_student_id_index` (`tenant_id`,`student_id`),
  KEY `tickets_ai_duplicate_of_id_foreign` (`ai_duplicate_of_id`),
  KEY `tickets_ai_event_id_foreign` (`ai_event_id`),
  KEY `tickets_tenant_id_status_priority_index` (`tenant_id`,`status`,`priority`),
  CONSTRAINT `tickets_ai_duplicate_of_id_foreign` FOREIGN KEY (`ai_duplicate_of_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ai_event_id_foreign` FOREIGN KEY (`ai_event_id`) REFERENCES `ai_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_assignee_id_foreign` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_escalated_to_id_foreign` FOREIGN KEY (`escalated_to_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tickets_support_team_id_foreign` FOREIGN KEY (`support_team_id`) REFERENCES `support_teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `topic_completions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `topic_completions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_completions_user_id_topic_id_unique` (`user_id`,`topic_id`),
  KEY `topic_completions_topic_id_foreign` (`topic_id`),
  KEY `topic_completions_tenant_id_index` (`tenant_id`),
  CONSTRAINT `topic_completions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_completions_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_completions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `topics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `day_number` smallint(5) unsigned DEFAULT NULL,
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`keywords`)),
  `summary` text DEFAULT NULL,
  `mock_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topics_tenant_id_index` (`tenant_id`),
  KEY `topics_module_id_index` (`module_id`),
  KEY `topics_module_id_day_number_index` (`module_id`,`day_number`),
  CONSTRAINT `topics_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topics_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tutor_conversations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tutor_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tutor_conversations_student_id_foreign` (`student_id`),
  KEY `tutor_conversations_lesson_id_foreign` (`lesson_id`),
  KEY `tutor_conversations_tenant_id_index` (`tenant_id`),
  KEY `tutor_conversations_tenant_id_student_id_last_message_at_index` (`tenant_id`,`student_id`,`last_message_at`),
  CONSTRAINT `tutor_conversations_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tutor_conversations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tutor_conversations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tutor_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tutor_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `cited_chunk_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cited_chunk_ids`)),
  `confidence` varchar(255) DEFAULT NULL,
  `escalated` tinyint(1) NOT NULL DEFAULT 0,
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `question_fingerprint` varchar(255) DEFAULT NULL,
  `ai_event_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tutor_messages_conversation_id_foreign` (`conversation_id`),
  KEY `tutor_messages_student_id_foreign` (`student_id`),
  KEY `tutor_messages_ticket_id_foreign` (`ticket_id`),
  KEY `tutor_messages_ai_event_id_foreign` (`ai_event_id`),
  KEY `tutor_messages_tenant_id_index` (`tenant_id`),
  KEY `tutor_messages_repeat_idx` (`tenant_id`,`student_id`,`question_fingerprint`,`created_at`),
  CONSTRAINT `tutor_messages_ai_event_id_foreign` FOREIGN KEY (`ai_event_id`) REFERENCES `ai_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tutor_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `tutor_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tutor_messages_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tutor_messages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tutor_messages_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `user_type` varchar(255) NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `anonymized_at` timestamp NULL DEFAULT NULL,
  `telemetry_consent_at` timestamp NULL DEFAULT NULL,
  `consent_version` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `leaderboard_opt_out` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_tenant_id_phone_unique` (`tenant_id`,`phone`),
  KEY `users_tenant_id_index` (`tenant_id`),
  KEY `users_user_type_index` (`user_type`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=312 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `voucher_issues`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voucher_issues` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `voucher_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `testimonial_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'issued',
  `discount_paise` bigint(20) unsigned DEFAULT NULL,
  `fee_plan_id` bigint(20) unsigned DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `applied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_issues_code_unique` (`code`),
  KEY `voucher_issues_voucher_id_foreign` (`voucher_id`),
  KEY `voucher_issues_user_id_foreign` (`user_id`),
  KEY `voucher_issues_testimonial_id_foreign` (`testimonial_id`),
  KEY `voucher_issues_fee_plan_id_foreign` (`fee_plan_id`),
  KEY `voucher_issues_tenant_id_index` (`tenant_id`),
  KEY `voucher_issues_tenant_id_user_id_status_index` (`tenant_id`,`user_id`,`status`),
  CONSTRAINT `voucher_issues_fee_plan_id_foreign` FOREIGN KEY (`fee_plan_id`) REFERENCES `fee_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `voucher_issues_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `voucher_issues_testimonial_id_foreign` FOREIGN KEY (`testimonial_id`) REFERENCES `testimonials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `voucher_issues_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `voucher_issues_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vouchers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vouchers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `value` bigint(20) unsigned NOT NULL,
  `max_discount_paise` bigint(20) unsigned DEFAULT NULL,
  `valid_days` int(10) unsigned DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `usage_limit` int(10) unsigned DEFAULT NULL,
  `per_user_limit` int(10) unsigned NOT NULL DEFAULT 1,
  `allow_stacking` tinyint(1) NOT NULL DEFAULT 0,
  `is_review_reward` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vouchers_course_id_foreign` (`course_id`),
  KEY `vouchers_tenant_id_index` (`tenant_id`),
  KEY `vouchers_tenant_id_active_is_review_reward_index` (`tenant_id`,`active`,`is_review_reward`),
  CONSTRAINT `vouchers_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhooks_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhooks_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `provider` varchar(255) NOT NULL,
  `event` varchar(255) DEFAULT NULL,
  `event_id` varchar(255) DEFAULT NULL,
  `signature_valid` tinyint(1) NOT NULL DEFAULT 0,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhooks_log_tenant_id_foreign` (`tenant_id`),
  KEY `webhooks_log_provider_event_index` (`provider`,`event`),
  KEY `webhooks_log_event_id_index` (`event_id`),
  CONSTRAINT `webhooks_log_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `zoom_licenses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zoom_licenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `zoom_user_id` varchar(255) NOT NULL,
  `mentor_id` bigint(20) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zoom_licenses_tenant_id_mentor_id_unique` (`tenant_id`,`mentor_id`),
  KEY `zoom_licenses_mentor_id_foreign` (`mentor_id`),
  KEY `zoom_licenses_tenant_id_index` (`tenant_id`),
  CONSTRAINT `zoom_licenses_mentor_id_foreign` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `zoom_licenses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:11
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_15_100001_create_tenants_table',1),(5,'2026_07_15_100002_add_tenant_id_to_users_table',1),(6,'2026_07_15_110001_create_roles_and_permissions_tables',1),(7,'2026_07_15_110002_create_audit_logs_table',1),(8,'2026_07_15_120001_add_auth_columns_to_users_table',1),(9,'2026_07_15_120002_create_staff_profiles_and_support_teams_tables',1),(10,'2026_07_15_120003_create_otp_codes_table',1),(11,'2026_07_15_130001_create_magic_links_table',1),(12,'2026_07_15_140001_create_curriculum_tables',1),(13,'2026_07_15_140002_create_batches_and_members_tables',1),(14,'2026_07_15_150001_create_live_sessions_attendance_recordings_tables',1),(15,'2026_07_15_160001_add_reminders_and_session_changes',1),(16,'2026_07_15_170702_create_personal_access_tokens_table',1),(17,'2026_07_15_180001_add_status_and_tagline_to_courses',1),(18,'2026_07_15_180002_create_leads_table',1),(19,'2026_07_15_190001_create_reviews_table',1),(20,'2026_07_15_190002_make_users_email_nullable',1),(21,'2026_07_16_100001_create_lead_stages_table',1),(22,'2026_07_16_100002_create_crm_assignment_rules_table',1),(23,'2026_07_16_100003_add_crm_columns_to_leads_table',1),(24,'2026_07_16_100004_create_contact_timeline_events_table',1),(25,'2026_07_16_100005_create_crm_tasks_table',1),(26,'2026_07_16_110001_create_fee_plans_table',1),(27,'2026_07_16_110002_create_instalments_table',1),(28,'2026_07_16_110003_create_payments_table',1),(29,'2026_07_16_110004_create_receipts_table',1),(30,'2026_07_16_110005_create_ledger_entries_table',1),(31,'2026_07_16_110006_create_webhooks_log_table',1),(32,'2026_07_16_120001_create_access_blocks_table',1),(33,'2026_07_16_120002_create_fee_reminders_table',1),(34,'2026_07_16_130001_create_message_templates_table',1),(35,'2026_07_16_130002_create_messages_table',1),(36,'2026_07_16_130003_create_in_app_notifications_table',1),(37,'2026_07_16_130004_create_message_preferences_table',1),(38,'2026_07_16_130005_add_last_replied_at_to_leads_table',1),(39,'2026_07_16_140001_create_conversion_nudges_table',1),(40,'2026_07_16_150001_create_vouchers_table',1),(41,'2026_07_16_150002_create_testimonials_table',1),(42,'2026_07_16_150003_create_voucher_issues_table',1),(43,'2026_07_16_160001_create_ticket_routes_table',1),(44,'2026_07_16_160002_create_tickets_table',1),(45,'2026_07_16_160003_create_ticket_messages_table',1),(46,'2026_07_16_160004_create_ticket_attachments_table',1),(47,'2026_07_16_160005_create_canned_responses_table',1),(48,'2026_07_16_160006_add_trainer_id_to_batches_table',1),(49,'2026_07_16_170001_create_monetization_settings_table',1),(50,'2026_07_16_170002_create_products_table',1),(51,'2026_07_16_170003_create_subscriptions_table',1),(52,'2026_07_16_170004_create_product_purchases_table',1),(53,'2026_07_16_170005_create_credit_wallets_table',1),(54,'2026_07_16_170006_create_credit_transactions_table',1),(55,'2026_07_16_170007_create_entitlements_table',1),(56,'2026_07_16_170008_create_quota_usages_table',1),(57,'2026_07_16_170009_add_enrolment_type_to_batch_members_table',1),(58,'2026_07_16_180001_create_ai_events_table',1),(59,'2026_07_16_180002_create_activity_events_table',1),(60,'2026_07_16_180003_create_coding_labs_table',1),(61,'2026_07_16_180004_create_code_submissions_table',1),(62,'2026_07_16_180005_create_student_scores_table',1),(63,'2026_07_16_180006_create_score_snapshots_table',1),(64,'2026_07_16_190001_create_knowledge_documents_table',1),(65,'2026_07_16_190002_create_knowledge_chunks_table',1),(66,'2026_07_16_190003_create_tutor_conversations_table',1),(67,'2026_07_16_190004_create_tutor_messages_table',1),(68,'2026_07_16_200001_create_quizzes_table',1),(69,'2026_07_16_200002_create_quiz_questions_table',1),(70,'2026_07_16_200003_create_quiz_attempts_table',1),(71,'2026_07_16_200004_create_assignments_table',1),(72,'2026_07_16_200005_create_assignment_submissions_table',1),(73,'2026_07_16_200006_create_assignment_grades_table',1),(74,'2026_07_16_200007_create_certificates_table',1),(75,'2026_07_16_200008_create_reports_table',1),(76,'2026_07_16_200009_create_lesson_notes_table',1),(77,'2026_07_16_200010_create_syllabuses_table',1),(78,'2026_07_17_100001_add_category_to_knowledge_documents_table',1),(79,'2026_07_17_100001_create_points_and_badges_tables',1),(80,'2026_07_17_100002_create_ticket_deflections_table',1),(81,'2026_07_17_110001_add_ai_triage_to_tickets_table',1),(82,'2026_07_17_110001_create_engagement_tables',1),(83,'2026_07_17_120001_create_mock_interview_tables',1),(84,'2026_07_17_150001_create_real_interview_bank_tables',1),(85,'2026_07_17_180001_add_voice_columns_to_mock_interviews',1),(86,'2026_07_17_210001_create_mentor_scheduling_tables',1),(87,'2026_07_17_230001_add_course_ids_to_mentor_profiles',1),(88,'2026_07_18_090001_create_cv_documents_table',1),(89,'2026_07_18_110001_create_cv_profiles_table',1),(90,'2026_07_18_140001_create_placement_tables',1),(91,'2026_07_18_170001_create_retention_tables',1),(92,'2026_07_18_180001_create_platform_settings_table',1),(93,'2026_07_18_190001_create_zoom_licenses_table',1),(94,'2026_07_18_200001_add_skill_to_mock_blueprints_table',1),(95,'2026_07_18_210001_create_career_boosters_table',1),(96,'2026_07_18_220001_create_market_jds_table',1),(97,'2026_07_18_230001_create_syllabus_recommendations_table',1),(98,'2026_07_18_240001_create_hiring_partner_feedback_table',1),(99,'2026_07_18_240002_create_salary_benchmarks_table',1),(100,'2026_07_18_240003_create_alumni_checkins_table',1),(101,'2026_07_18_250001_create_pri_calibrations_table',1),(102,'2026_07_18_260001_create_job_feed_sources_table',1),(103,'2026_07_18_260002_create_job_feed_items_table',1),(104,'2026_07_18_270001_create_job_feed_saves_table',1),(105,'2026_07_18_280001_add_job_feed_item_to_applications',1),(106,'2026_07_18_290001_create_data_requests_table',1),(107,'2026_07_18_300001_add_telemetry_consent_to_users',1),(108,'2026_07_19_100000_create_market_signals_table',1),(109,'2026_07_19_130000_create_funding_news_table',1),(110,'2026_07_19_150000_add_kind_to_funding_news',1),(111,'2026_07_21_000001_add_is_active_to_users_table',1),(112,'2026_07_21_100001_add_pdf_path_to_lesson_notes',1),(113,'2026_07_21_100002_create_lesson_videos_table',1),(114,'2026_07_21_120000_create_project_specs_table',1),(115,'2026_07_22_100000_create_flashcards_table',1),(116,'2026_07_22_100001_create_flashcard_reviews_table',1),(117,'2026_07_22_110000_add_required_mocks_to_modules_table',1),(118,'2026_07_22_110001_create_module_mock_requirements_table',1),(119,'2026_07_22_120000_add_wrapped_up_at_to_live_sessions_table',1),(120,'2026_07_22_130000_create_placement_stories_table',1),(121,'2026_07_22_130001_create_course_interview_questions_table',1),(122,'2026_07_22_130002_add_author_meta_to_reviews_table',1),(123,'2026_07_22_140000_create_review_intake_table',1),(124,'2026_07_22_150000_add_review_gate_to_testimonials',1),(125,'2026_07_22_160000_add_is_sample_to_placement_stories',1),(126,'2026_07_22_170000_create_batch_module_trainers_table',1),(127,'2026_07_22_180000_create_batch_mentors_table',1),(128,'2026_07_23_090000_add_kind_host_and_license_to_live_sessions',1),(129,'2026_07_23_100000_add_cloud_url_to_recordings',1),(130,'2026_07_23_120000_add_job_prep_columns',1),(131,'2026_07_23_150000_create_job_kit_unlocks_table',1),(132,'2026_07_23_180000_add_day_builder_columns',1),(133,'2026_07_27_160000_remove_implicit_on_update_from_timestamps',2),(134,'2026_07_27_170000_add_bootcamp_payment_nudge_template',3),(135,'2026_07_27_180000_add_class_reminder_email_template',4),(136,'2026_07_27_200000_add_batch_credentials_template',5),(137,'2026_07_27_210000_add_class_scheduled_template',5),(138,'2026_07_27_220000_add_batch_announcement_template',6),(139,'2026_07_28_100000_add_masterclass_video_url_to_courses',7),(140,'2026_07_28_130000_reformat_batch_credentials_email_template',8),(141,'2026_07_28_150000_otp_wording_for_batch_credentials_template',9),(142,'2026_07_28_160000_course_dynamic_batch_message',10),(143,'2026_07_28_180000_mentor_pack_and_booking_message',11),(144,'2026_07_30_101415_add_is_room_to_mock_interviews_table',12),(145,'2026_07_30_112410_add_fee_to_courses_table',13),(146,'2026_07_30_193552_create_site_contents_table',14);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:11
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,1,1,'DE','Data Engineering','data-engineering','live',NULL,'Pipelines, warehouses, and the modern data stack.',NULL,'Data Engineering — built from real interviews.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(2,1,1,'DC','DevOps & Cloud','devops-cloud','live',NULL,'Ship, scale, and run production systems.',NULL,'DevOps & Cloud — built from real interviews.',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(3,1,1,'PB','Python Backend','python-backend','live',NULL,'APIs, databases, and production Python.',NULL,'Python Backend — built from real interviews.',2,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(4,1,1,'DA','Data Analytics','data-analytics','live',NULL,'SQL, dashboards, and decisions from data.',NULL,'Data Analytics — built from real interviews.',3,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(5,1,1,'AA','Agentic AI','agentic-ai','coming_soon',NULL,'Build with LLMs, agents, and tools.',NULL,'Agentic AI — built from real interviews.',4,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(6,1,1,'CS','Cyber Security','cyber-security','coming_soon',NULL,'Defend real systems against real attacks.',NULL,'Cyber Security — built from real interviews.',5,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(7,1,1,'SN','ServiceNow','servicenow','coming_soon',NULL,'The enterprise workflow platform.',NULL,'ServiceNow — built from real interviews.',6,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(8,1,NULL,'PRAC','Practice','practice','live',NULL,NULL,NULL,NULL,0,'2026-07-24 06:53:40','2026-07-24 06:53:40');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:11
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` VALUES (1,1,1,'Foundations',0,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(2,1,1,'Core Skills',1,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(3,1,1,'Capstone',2,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(4,1,2,'Foundations',0,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(5,1,2,'Core Skills',1,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(6,1,2,'Capstone',2,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(7,1,3,'Foundations',0,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(8,1,3,'Core Skills',1,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(9,1,3,'Capstone',2,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(10,1,4,'Foundations',0,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(11,1,4,'Core Skills',1,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(12,1,4,'Capstone',2,NULL,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(13,1,8,'Warm-ups',1,NULL,'2026-07-24 06:53:40','2026-07-24 06:53:40');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:11
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `topics`
--

LOCK TABLES `topics` WRITE;
/*!40000 ALTER TABLE `topics` DISABLE KEYS */;
INSERT INTO `topics` VALUES (1,1,1,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(2,1,1,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(3,1,2,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(4,1,2,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(5,1,3,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(6,1,3,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(7,1,4,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(8,1,4,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(9,1,5,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(10,1,5,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(11,1,6,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(12,1,6,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(13,1,7,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(14,1,7,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(15,1,8,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(16,1,8,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(17,1,9,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(18,1,9,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(19,1,10,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(20,1,10,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(21,1,11,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(22,1,11,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(23,1,12,'Concepts',0,1,'[\"fundamentals\",\"core concepts\"]','The core ideas of this module, from zero.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(24,1,12,'Hands-on Lab',1,2,'[\"practice\",\"hands-on\"]','Apply what you learned in a guided lab.',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(25,1,13,'Basics',1,NULL,NULL,NULL,0,'2026-07-24 06:53:40','2026-07-24 06:53:40');
/*!40000 ALTER TABLE `topics` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:11
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `lessons`
--

LOCK TABLES `lessons` WRITE;
/*!40000 ALTER TABLE `lessons` DISABLE KEYS */;
INSERT INTO `lessons` VALUES (1,1,1,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(2,1,1,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(3,1,2,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(4,1,2,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(5,1,3,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(6,1,3,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(7,1,4,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(8,1,4,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(9,1,5,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(10,1,5,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(11,1,6,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(12,1,6,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(13,1,7,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(14,1,7,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(15,1,8,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(16,1,8,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(17,1,9,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(18,1,9,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(19,1,10,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(20,1,10,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(21,1,11,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(22,1,11,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(23,1,12,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(24,1,12,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(25,1,13,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(26,1,13,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(27,1,14,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(28,1,14,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(29,1,15,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(30,1,15,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(31,1,16,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(32,1,16,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(33,1,17,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(34,1,17,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(35,1,18,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(36,1,18,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(37,1,19,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(38,1,19,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(39,1,20,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(40,1,20,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(41,1,21,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(42,1,21,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(43,1,22,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(44,1,22,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(45,1,23,'Concepts: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(46,1,23,'Concepts: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(47,1,24,'Hands-on Lab: Live Session','live_class',0,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(48,1,24,'Hands-on Lab: Assignment','assignment',1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(49,1,25,'Sum two numbers','coding_lab',1,'2026-07-24 06:53:40','2026-07-24 06:53:40'),(50,1,1,'Module 1 Check','quiz',99,'2026-07-24 06:53:41','2026-07-24 06:53:41'),(51,1,1,'Intro to functions — class notes','notes',97,'2026-07-24 06:53:41','2026-07-24 06:53:41');
/*!40000 ALTER TABLE `lessons` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:11
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'cv-3pack','CV generations · 3-pack','cv','pack',9900,3,NULL,NULL,1,'2026-07-24 06:53:40','2026-07-24 06:53:40'),(2,1,'voice-single','Voice mock · single session','voice_mock','pack',24900,1,NULL,NULL,0,'2026-07-24 06:53:40','2026-07-29 15:17:09'),(3,1,'voice-3pack','Voice interviews · 3 sessions','voice_mock','pack',100000,3,NULL,NULL,1,'2026-07-24 06:53:40','2026-07-29 15:17:09'),(4,1,'mentor-extra','Extra mentor 1:1','mentor','pack',49900,1,NULL,NULL,1,'2026-07-24 06:53:40','2026-07-29 15:17:09'),(5,1,'job-kit','Interview Kit · one job','job_kit','pack',10000,1,NULL,NULL,1,'2026-07-24 06:53:40','2026-07-24 06:53:40'),(6,1,'job-kit-mentor','Interview Kit + mentor 1:1 · one job','job_kit','pack',29900,1,NULL,NULL,1,'2026-07-24 06:53:40','2026-07-24 06:53:40'),(7,1,'career-plus','Career+ (monthly)','career_plus','subscription',49900,0,30,NULL,1,'2026-07-24 06:53:40','2026-07-24 06:53:40'),(8,1,'voice-6pack','Voice interviews · 6 sessions','voice_mock','pack',150000,6,NULL,NULL,1,'2026-07-29 15:17:09','2026-07-29 15:17:09');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:11
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super-admin','Super Admin',NULL,1,'2026-07-24 06:53:37','2026-07-24 06:53:37'),(2,'admin','Admin (Institute)',NULL,1,'2026-07-24 06:53:37','2026-07-24 06:53:37'),(3,'trainer','Trainer',NULL,1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(4,'mentor','Mentor',NULL,1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(5,'counselor','Counselor',NULL,1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(6,'placement-officer','Placement Officer',NULL,1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(7,'support-agent','Support Agent',NULL,1,'2026-07-24 06:53:38','2026-07-24 06:53:38'),(8,'student','Student',NULL,0,'2026-07-24 06:53:38','2026-07-24 06:53:38');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:12
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'manage-tenants','Manage Tenants','Provision and configure tenants','2026-07-24 06:53:37','2026-07-24 06:53:37'),(2,'manage-users','Manage Users','Create and manage user accounts','2026-07-24 06:53:37','2026-07-24 06:53:37'),(3,'manage-roles','Manage Roles','Assign roles and permissions','2026-07-24 06:53:37','2026-07-24 06:53:37'),(4,'manage-curriculum','Manage Curriculum','Manage programs, courses, modules, topics, lessons','2026-07-24 06:53:37','2026-07-24 06:53:37'),(5,'manage-batches','Manage Batches','Create and manage batches','2026-07-24 06:53:37','2026-07-24 06:53:37'),(6,'manage-rosters','Manage Rosters','Manage batch rosters','2026-07-24 06:53:37','2026-07-24 06:53:37'),(7,'teach-classes','Teach Classes','Run live classes and mark attendance','2026-07-24 06:53:37','2026-07-24 06:53:37'),(8,'mentor-students','Mentor Students','Mentor students and take bookings','2026-07-24 06:53:37','2026-07-24 06:53:37'),(9,'manage-leads','Manage Leads','Work the CRM lead pipeline','2026-07-24 06:53:37','2026-07-24 06:53:37'),(10,'manage-fees','Manage Fees','Manage fee plans, payments and receipts','2026-07-24 06:53:37','2026-07-24 06:53:37'),(11,'manage-messaging','Manage Messaging','Manage message templates and the delivery log','2026-07-24 06:53:37','2026-07-24 06:53:37'),(12,'manage-vouchers','Manage Vouchers','Manage vouchers and moderate testimonials','2026-07-24 06:53:37','2026-07-24 06:53:37'),(13,'manage-monetization','Manage Monetization','Manage products, pricing, subscriptions and revenue','2026-07-24 06:53:37','2026-07-24 06:53:37'),(14,'manage-placements','Manage Placements','Manage placement pipeline and jobs','2026-07-24 06:53:37','2026-07-24 06:53:37'),(15,'handle-tickets','Handle Tickets','Work the student support desk','2026-07-24 06:53:37','2026-07-24 06:53:37'),(16,'view-student-portal','View Student Portal','Access the student learning portal','2026-07-24 06:53:37','2026-07-24 06:53:37');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:12
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: browsejobs_lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'BrowseJobs','browsejobs',NULL,'{\"colors\":{\"ink_navy\":\"#0A1220\",\"deep_navy\":\"#0E3FA9\",\"trust_blue\":\"#1B6DF0\",\"sky\":\"#E7F1FE\",\"verify_green\":\"#0BA860\",\"warn_red\":\"#D64545\",\"amber\":\"#F5A623\",\"paper\":\"#F6F9FE\",\"line\":\"#DCE6F5\",\"muted\":\"#5A6B85\"},\"fonts\":{\"display\":\"Sora\",\"body\":\"Inter\",\"mono\":\"IBM Plex Mono\"}}','{\"crm\":true,\"ai_coach\":true,\"mock_interviewer\":true,\"placement\":true,\"leaderboards\":true}','enterprise','active','{COURSE}-{YYYYMM}-{seq}','2026-07-24 06:53:37','2026-07-30 12:04:30');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 11:49:12
